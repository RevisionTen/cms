<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\PageRead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
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
     * @param EntityManagerInterface $em
     * @param string                 $pageUuid
     *
     * @return Response
     */
    private function renderPage(EntityManagerInterface $em, string $pageUuid): Response
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

        return $this->render($template, [
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
    public function page(EntityManagerInterface $em, string $pageUuid): Response
    {
        return $this->renderPage($em, $pageUuid);
    }

    /**
     * @Route("/", name="cms_page_frontpage")
     * @Route("/{_locale}", name="cms_page_frontpage_locale", requirements={
     *     "_locale": "ad|ae|af|ag|ai|al|am|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bl|bm|bn|bo|bq|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cw|cx|cy|cz|de|dj|dk|dm|do|dz|ec|ee|en|eg|eh|er|es|et|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mf|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|ss|st|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tr|tt|tv|tw|tz|ua|ug|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|za|zm|zw"
     * })
     *
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function frontpage(EntityManagerInterface $em): Response
    {
        /** @var Alias $alias */
        $alias = $em->getRepository(Alias::class)->findOneBy([
            'path' => '/',
        ]);
        $pageStreamRead = $alias ? $alias->getPageStreamRead() : null;
        $pageUuid = $pageStreamRead ? $pageStreamRead->getUuid() : null;

        return $this->renderPage($em, $pageUuid);
    }

    /**
     * @Route("/{_locale}/{path}", name="cms_page_alias_locale", requirements={
     *     "path"=".+",
     *     "_locale": "ad|ae|af|ag|ai|al|am|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bl|bm|bn|bo|bq|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cw|cx|cy|cz|de|dj|dk|dm|do|dz|ec|ee|en|eg|eh|er|es|et|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mf|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|ss|st|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tr|tt|tv|tw|tz|ua|ug|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|za|zm|zw"
     * })
     * @Route("/{path}", name="cms_page_alias", requirements={"path"=".+"})
     *
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function alias(string $path, EntityManagerInterface $em): Response
    {
        /** @var Alias|null $alias */
        $alias = $em->getRepository(Alias::class)->findOneBy([
            'path' => '/'.$path,
        ]);

        if (null === $alias) {
            throw $this->createNotFoundException();
        }

        $pageStreamRead = $alias->getPageStreamRead();

        // Page not set or page unpublished.
        if (null === $pageStreamRead || !$pageStreamRead->isPublished()) {
            $redirect = $alias->getRedirect();
            if ($redirect) {
                // Redirect request.
                $redirectResponse = $this->redirect($redirect);
                // Redirect expires immediately to prevent browser caching.
                $redirectResponse->setExpires(new \DateTime());

                return $redirectResponse;
            } else {
                // Show not found.
                throw $this->createNotFoundException();
            }
        }

        $pageUuid = $pageStreamRead->getUuid();

        return $this->renderPage($em, $pageUuid);
    }
}