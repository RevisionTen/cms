<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use DateTime;
use Exception;
use RevisionTen\CMS\Event\PageRenderEvent;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\PageRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CMS\Services\CacheService;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\Services\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function class_exists;
use function explode;
use function is_string;
use function method_exists;
use function strtotime;

/**
 * Class FrontendController.
 */
class FrontendController extends AbstractController
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var PageService
     */
    protected $pageService;

    /**
     * @var CacheService
     */
    protected $cacheService;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var array
     */
    protected $config;

    public function __construct(EventDispatcherInterface $eventDispatcher, PageService $pageService, CacheService $cacheService, EntityManagerInterface $entityManager, array $config)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageService = $pageService;
        $this->cacheService = $cacheService;
        $this->entityManager = $entityManager;
        $this->config = $config;
    }

    /**
     * @Route("/sitemap.xml", name="cms_page_sitemap")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function sitemap(Request $request): Response
    {
        /**
         * @var Alias[] $aliases
         */
        $aliases = $this->entityManager->getRepository(Alias::class)->findAllMatchingAlias($request->get('websiteId'), $request->getLocale());

        $response = $this->render('@CMS/Frontend/sitemap.xml.twig', [
            'aliases' => $aliases,
        ]);

        $response->headers->set('Content-Type', 'xml');

        return $response;
    }

    /**
     * Render a page by a given uuid.
     *
     * @param string     $pageUuid
     * @param Alias|null $alias
     *
     * @return Response
     */
    public function renderPage(string $pageUuid, Alias $alias = null): Response
    {
        // Get page from cache.
        $pageData = $this->cacheService->get($pageUuid);

        if (null === $pageData) {
            // Get Page from read model.
            $pageRead = $this->getPageRead($pageUuid);
            $pageData = $pageRead ? $pageRead->getPayload() : false;

            if ($pageData) {
                // Populate cache.
                $this->cacheService->put($pageUuid, $pageRead->getVersion(), $pageData);
            }
        }

        if (!$pageData) {
            return new Response('404', Response::HTTP_NOT_FOUND);
        }

        // Get the pages website.
        $website = isset($pageData['website']) ? $this->entityManager->getRepository(Website::class)->find($pageData['website']) : null;

        return $this->getPageResponse($pageData, $website, $alias);
    }

    /**
     * @param array        $pageData
     * @param Website|null $website
     * @param Alias|null   $alias
     *
     * @return Response
     */
    public function getPageResponse(array $pageData, Website $website = null, Alias $alias = null): Response
    {
        // Get the page template from the template name.
        $templateName = $pageData['template'];
        $template = $this->config['page_templates'][$templateName]['template'] ?? '@cms/layout.html.twig';

        // Hydrate the page with doctrine entities.
        $page = $this->pageService->hydratePage($pageData);

        $context = [
            'website' => $website,
            'alias' => $alias,
            'page' => $page,
            'edit' => false,
            'config' => $this->config,
        ];

        // Send PageRender event.
        $event = new PageRenderEvent($page, $this->config, $website, $alias);
        $this->eventDispatcher->dispatch($event, PageRenderEvent::NAME);

        return $this->render($template, $context);
    }

    /**
     * @Route("/", name="cms_page_frontpage")
     * @Route("/{_locale}", name="cms_page_frontpage_locale", requirements={
     *     "_locale":
     *     "ad|ae|af|ag|ai|al|am|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bl|bm|bn|bo|bq|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cw|cx|cy|cz|de|dj|dk|dm|do|dz|ec|ee|en|eg|eh|er|es|et|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mf|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|ss|st|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tr|tt|tv|tw|tz|ua|ug|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|za|zm|zw"
     * })
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function frontpage(Request $request): Response
    {
        return $this->alias($request, '');
    }

    /**
     * @Route("/{_locale}/{path}", name="cms_page_alias_locale", requirements={
     *     "path"=".+",
     *     "_locale": "ad|ae|af|ag|ai|al|am|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bl|bm|bn|bo|bq|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cw|cx|cy|cz|de|dj|dk|dm|do|dz|ec|ee|en|eg|eh|er|es|et|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mf|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|ss|st|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tr|tt|tv|tw|tz|ua|ug|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|za|zm|zw"
     * })
     * @Route("/{path}", name="cms_page_alias", requirements={"path"=".+"})
     *
     * @param Request $request
     * @param string  $path
     *
     * @return Response
     * @throws Exception
     */
    public function alias(Request $request, string $path): Response
    {
        /**
         * @var Alias|null $alias
         */
        $alias = $this->entityManager->getRepository(Alias::class)->findMatchingAlias('/'.$path, $request->get('websiteId'), $request->getLocale());

        if (null === $alias) {
            throw $this->createNotFoundException();
        }

        $pageStreamRead = $alias->getPageStreamRead();
        $controller = $alias->getController();

        $response = null;
        $redirect = $alias->getRedirect();

        if (null !== $pageStreamRead && $pageStreamRead->isPublished()) {
            // Render PageStreamRead Entity.
            $pageUuid = $pageStreamRead->getUuid();
            $response = $this->renderPage($pageUuid, $alias);
        } elseif (null !== $controller) {
            // Forward request to controller.
            [$class, $method] = explode('::', $controller);
            if (class_exists($class) && method_exists($class, $method)) {
                $response = $this->forward($controller, [
                    'alias' => $alias,
                ]);
            } else {
                throw $this->createNotFoundException();
            }
        } elseif ($redirect) {
            // Redirect request.
            $redirectResponse = $this->redirect($redirect, $alias->getRedirectCode());
            // Redirect expires immediately to prevent browser caching.
            $redirectResponse->setExpires(new DateTime());
            $response = $redirectResponse;
        } else {
            // Show not found.
            throw $this->createNotFoundException();
        }

        // Add tracking cookies.
        $response = $this->addTrackingCookies($request, $response);

        return $response;
    }

    /**
     * @param RequestStack  $requestStack
     * @param SearchService $searchService
     *
     * @return Response
     */
    public function fulltextSearch(RequestStack $requestStack, SearchService $searchService): Response
    {
        $request = $requestStack->getMainRequest();

        $query = $request ? $request->get('q') : null;

        $results = !empty($query) && is_string($query) ? $searchService->getFulltextResults($query) : null;

        return $this->render('@CMS/Frontend/Search/fulltext.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     */
    private function addTrackingCookies(Request $request, Response $response): Response
    {
        $expire = strtotime('now + 1 year');
        $utm_source = $request->get('utm_source');
        if ($utm_source) {
            $response->headers->setCookie(new Cookie('cms_utm_source', $utm_source, $expire));
        }
        $utm_medium = $request->get('utm_medium');
        if ($utm_medium) {
            $response->headers->setCookie(new Cookie('cms_utm_medium', $utm_medium, $expire));
        }
        $utm_campaign = $request->get('utm_campaign');
        if ($utm_campaign) {
            $response->headers->setCookie(new Cookie('cms_utm_campaign', $utm_campaign, $expire));
        }
        $utm_term = $request->get('utm_term');
        if ($utm_term) {
            $response->headers->setCookie(new Cookie('cms_utm_term', $utm_term, $expire));
        }
        $utm_content = $request->get('utm_content');
        if ($utm_content) {
            $response->headers->setCookie(new Cookie('cms_utm_content', $utm_content, $expire));
        }

        return $response;
    }

    /**
     * Gets a PageRead entity for a Page Aggregate by its uuid.
     *
     * @param string $pageUuid
     *
     * @return PageRead|null
     */
    private function getPageRead(string $pageUuid): ?PageRead
    {
        return $this->entityManager->getRepository(PageRead::class)->findOneBy(['uuid' => $pageUuid]);
    }
}
