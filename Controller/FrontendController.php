<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\PageRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Services\PageService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;

/**
 * Class FrontendController.
 */
class FrontendController extends Controller
{
    /**
     * @Route("/sitemap.xml", name="cms_page_sitemap")
     *
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function sitemap(EntityManagerInterface $em): Response
    {
        /** @var Alias[] $aliases */
        $aliases = $em->getRepository(Alias::class)->findAll();

        $response = $this->render('@cms/sitemap.xml.twig', [
            'aliases' => $aliases,
        ]);

        $response->headers->set('Content-Type', 'xml');

        return $response;
    }

    /**
     * Gets a PageRead entity for a Page Aggregate by its uuid.
     *
     * @param EntityManagerInterface $em
     * @param string                 $pageUuid
     *
     * @return PageRead|null
     */
    private function getPageRead(EntityManagerInterface $em, string $pageUuid): ?PageRead
    {
        /** @var PageRead $page */
        $pageRead = $em->getRepository(PageRead::class)->findOneByUuid($pageUuid);

        return $pageRead;
    }

    /**
     * Render a page by a given uuid.
     *
     * @param PageService            $pageService
     * @param EntityManagerInterface $em
     * @param string                 $pageUuid
     * @param Alias|null             $alias
     *
     * @return Response
     */
    private function renderPage(PageService $pageService, EntityManagerInterface $em, string $pageUuid, Alias $alias = null): Response
    {
        $config = $this->getParameter('cms');
        $cacheEnabled = extension_loaded('apcu') && ini_get('apc.enabled');

        if ($cacheEnabled) {
            // Cache is enabled.
            $cache = new ApcuAdapter();
            $page = $cache->getItem($pageUuid);

            if ($page->isHit()) {
                // Get Page from cache.
                $pageData = $page->get();
            } else {
                // Get Page from read model.
                $pageRead = $this->getPageRead($em, $pageUuid);
                $pageData = $pageRead ? $pageRead->getPayload() : false;

                // Visitor triggers a write to cache.
                // TODO: Replace with function in backend that warms up the apc cache.
                if ($pageData) {
                    // Persist Page to cache.
                    $page->set($pageData);
                    $cache->save($page);
                }
            }
        } else {
            // Get Page from read model.
            $pageRead = $this->getPageRead($em, $pageUuid);
            $pageData = $pageRead ? $pageRead->getPayload() : false;
        }

        if (!$pageData) {
            return new Response('404', Response::HTTP_NOT_FOUND);
        }

        // Get the page template from the template name.
        $templateName = $pageData['template'];
        $template = $config['page_templates'][$templateName]['template'] ?? '@cms/layout.html.twig';

        // Hydrate the page with doctrine entities.
        $pageData = $pageService->hydratePage($pageData);

        return $this->render($template, [
            'alias' => $alias,
            'page' => $pageData,
            'edit' => false,
            'config' => $config,
        ]);
    }

    /**
     * Display a frontend page.
     *
     * @Route("/admin/show/{pageUuid}", name="cms_page_show")
     *
     * @param EntityManagerInterface $em
     * @param string                 $pageUuid
     *
     * @return Response
     */
    public function page(PageService $pageService, EntityManagerInterface $em, string $pageUuid): Response
    {
        return $this->renderPage($pageService, $em, $pageUuid);
    }

    /**
     * @Route("/", name="cms_page_frontpage")
     * @Route("/{_locale}", name="cms_page_frontpage_locale", requirements={
     *     "_locale": "ad|ae|af|ag|ai|al|am|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bl|bm|bn|bo|bq|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cw|cx|cy|cz|de|dj|dk|dm|do|dz|ec|ee|en|eg|eh|er|es|et|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mf|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|ss|st|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tr|tt|tv|tw|tz|ua|ug|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|za|zm|zw"
     * })
     *
     * @param PageService            $pageService
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function frontpage(PageService $pageService, EntityManagerInterface $em): Response
    {
        /** @var Alias $alias */
        $alias = $em->getRepository(Alias::class)->findOneBy([
            'path' => '/',
        ]);
        $pageStreamRead = $alias ? $alias->getPageStreamRead() : null;
        $pageUuid = $pageStreamRead ? $pageStreamRead->getUuid() : null;

        if (null === $pageUuid || null === $alias) {
            throw $this->createNotFoundException();
        }

        $response = $this->renderPage($pageService, $em, $pageUuid, $alias);

        // Add tracking cookies.
        $response = $this->addTrackingCookies($request, $response);

        return $response;
    }

    /**
     * @Route("/{_locale}/{path}", name="cms_page_alias_locale", requirements={
     *     "path"=".+",
     *     "_locale": "ad|ae|af|ag|ai|al|am|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bl|bm|bn|bo|bq|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cw|cx|cy|cz|de|dj|dk|dm|do|dz|ec|ee|en|eg|eh|er|es|et|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mf|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|ss|st|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tr|tt|tv|tw|tz|ua|ug|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|za|zm|zw"
     * })
     * @Route("/{path}", name="cms_page_alias", requirements={"path"=".+"})
     *
     * @param Request                $request
     * @param string                 $path
     * @param EntityManagerInterface $em
     * @param PageService            $pageService
     *
     * @return Response
     */
    public function alias(Request $request, string $path, PageService $pageService, EntityManagerInterface $em): Response
    {
        /** @var Alias|null $alias */
        $alias = $em->getRepository(Alias::class)->findOneBy([
            'path' => '/'.$path,
        ]);

        if (null === $alias) {
            throw $this->createNotFoundException();
        }

        $pageStreamRead = $alias->getPageStreamRead();
        $controller = $alias->getController();

        $response = null;

        if (null !== $pageStreamRead) {
            if ($pageStreamRead->isPublished()) {
                // Render PageStreamRead Entity.
                $pageUuid = $pageStreamRead->getUuid();
                $response = $this->renderPage($pageService, $em, $pageUuid, $alias);
            } else {
                // Page not set or page unpublished.
                $redirect = $alias->getRedirect();
                if ($redirect) {
                    // Redirect request.
                    $redirectResponse = $this->redirect($redirect);
                    // Redirect expires immediately to prevent browser caching.
                    $redirectResponse->setExpires(new \DateTime());

                    $response = $redirectResponse;
                } else {
                    // Show not found.
                    throw $this->createNotFoundException();
                }
            }
        } elseif (null !== $controller) {
            // Forward request to controller.
            $parts = explode('::', $controller);
            $class = $parts[0];
            $method = $parts[1];
            if (class_exists($class) && method_exists($class, $method)) {
                $response = $this->forward($controller, [
                    'alias' => $alias,
                ]);
            } else {
                throw $this->createNotFoundException();
            }
        } else {
            throw $this->createNotFoundException();
        }

        // Add tracking cookies.
        $response = $this->addTrackingCookies($request, $response);

        return $response;
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
}
