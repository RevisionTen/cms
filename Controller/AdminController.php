<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\CMSBundle;
use RevisionTen\CMS\Event\PageSubmitEvent;
use RevisionTen\CMS\Model\FileRead;
use RevisionTen\CMS\Model\MenuRead;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\RoleRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CMS\Services\CacheService;
use RevisionTen\CQRS\Model\EventStreamObject;
use RevisionTen\Forms\Model\FormRead;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use function extension_loaded;
use function function_exists;
use function gethostbyname;
use function ini_get;

/**
 * Class AdminController.
 *
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
    /**
     * @var array
     */
    private $config;

    /**
     * AdminController constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param RequestStack $requestStack
     *
     * @return Response
     */
    public function websiteChooser(RequestStack $requestStack): Response
    {
        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $request = $requestStack->getMasterRequest();
        $currentWebsite = $request ? $request->get('currentWebsite') : null;
        $websites = $user->getWebsites();

        return $this->render('@cms/Admin/Website/chooser.html.twig', [
            'currentWebsite' => $currentWebsite,
            'websites' => $websites,
        ]);
    }

    /**
     * @Route("/set-website/{website}", name="cms_set_website")
     *
     * @param SessionInterface $session
     * @param int              $website
     *
     * @return RedirectResponse
     */
    public function setCurrentWebsite(SessionInterface $session, int $website): RedirectResponse
    {
        $session->set('currentWebsite', $website);

        $response = $this->redirectToRoute('cms_dashboard');
        $response->headers->setCookie(new Cookie('cms_current_website', (string) $website, strtotime('now + 1 year')));

        return $response;
    }

    /**
     * Get the title from the website id.
     *
     * @param EntityManagerInterface $entityManager
     * @param int                    $id
     *
     * @return Response
     */
    public function websiteTitle(EntityManagerInterface $entityManager, int $id): Response
    {
        /**
         * @var Website $website
         */
        $website = $entityManager->getRepository(Website::class)->find($id);

        $fallbackWebsite = new Website();
        $fallbackWebsite->setId(0);
        $fallbackWebsite->setTitle('unknown');

        return $this->render('@cms/Admin/Website/website_info.html.twig', [
            'website' => $website ?? $fallbackWebsite,
        ]);
    }

    /**
     * Get the username from the user id.
     *
     * @param EntityManagerInterface $entityManager
     * @param int                    $userId
     * @param string                 $template
     *
     * @return Response
     *
     */
    public function userName(EntityManagerInterface $entityManager, int $userId, string $template = '@cms/Admin/User/small.html.twig'): Response
    {
        if (-1 === $userId) {
            $unknownUser = new UserRead();
            $unknownUser->setUsername('System');
        } else {
            /**
             * @var UserRead $user
             */
            $user = $entityManager->getRepository(UserRead::class)->find($userId);

            $unknownUser = new UserRead();
            $unknownUser->setUsername('Unknown');
        }

        return $this->render($template, [
            'user' => $user ?? $unknownUser,
        ]);
    }

    /**
     * Get the aggregate title from the uuid.
     *
     * @param TranslatorInterface $translator
     * @param string              $uuid
     *
     * @return Response
     */
    public function uuidTitle(TranslatorInterface $translator, string $uuid): Response
    {
        $title = $uuid;

        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var PageStreamRead|null $pageStreamRead
         */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->findOneBy([ 'uuid' => $uuid ]);
        /**
         * @var FormRead|null $formRead
         */
        $formRead = $em->getRepository(FormRead::class)->findOneBy([ 'uuid' => $uuid ]);
        /**
         * @var UserRead|null $userRead
         */
        $userRead = $em->getRepository(UserRead::class)->findOneBy([ 'uuid' => $uuid ]);
        /**
         * @var MenuRead|null $menuRead
         */
        $menuRead = $em->getRepository(MenuRead::class)->findOneBy([ 'uuid' => $uuid ]);
        /**
         * @var FileRead|null $fileRead
         */
        $fileRead = $em->getRepository(FileRead::class)->findOneBy([ 'uuid' => $uuid ]);
        /**
         * @var RoleRead|null $roleRead
         */
        $roleRead = $em->getRepository(RoleRead::class)->findOneBy([ 'uuid' => $uuid ]);

        if ($pageStreamRead) {
            $title = $pageStreamRead->getTitle();
        } elseif ($formRead) {
            $title = $formRead->getTitle();
        } elseif ($userRead) {
            $title = $userRead->getUsername();
        } elseif ($menuRead) {
            $title = $translator->trans($menuRead->getTitle());
        } elseif ($fileRead) {
            $title = $fileRead->getTitle();
        } elseif ($roleRead) {
            $title = $roleRead->getTitle();
        }

        return new Response($title);
    }

    /**
     * @Route("", name="cms_admin")
     * @Route("/", name="cms_admin_slash")
     * @Route("/dashboard", name="cms_dashboard")
     *
     * @param Request                $request
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function dashboardAction(Request $request, EntityManagerInterface $em): Response
    {
        // Get latest non page events.
        $nonPageEvents = $this->getNonePageEvents($em);

        // Get latest page events.
        $currentWebsite = (int) $request->get('currentWebsite');
        $pageEvents = $this->getPageEvents($em, $currentWebsite);

        return $this->render('@cms/Admin/dashboard.html.twig', [
            'nonPageEvents' => $nonPageEvents,
            'pageEvents' => $pageEvents,
        ]);
    }

    /**
     * @Route("/system-info", name="cms_systeminfo")
     *
     * @param EntityManagerInterface $em
     * @param CacheService           $cacheService
     *
     * @return Response
     */
    public function systemInfoAction(EntityManagerInterface $em, CacheService $cacheService): Response
    {
        return $this->render('@cms/Admin/system-info.html.twig', [
            'cache_enabled' => $cacheService->isCacheEnabled(),
            'symfony_version' => Kernel::VERSION,
            'cms_version' => CMSBundle::VERSION,
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION,
            'apc_enabled' => (extension_loaded('apcu') && ini_get('apc.enabled') && function_exists('apcu_clear_cache')),
            'memory_limit' => ini_get('memory_limit'),
            'upload_limit' => ini_get('upload_max_filesize'),
            'post_limit' => ini_get('post_max_size'),
            'execution_limit' => ini_get('max_execution_time'),
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']),
            'database_name' => $em->getConnection()->getDatabase(),
        ]);
    }

    /**
     * @Route("/edit-aggregate", name="cms_edit_aggregate")
     *
     * @param Request                $request
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function editAggregateAction(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('page_edit');

        // Get Preview Size.
        $cookies = [];
        if ($previewSize = $request->get('previewSize')) {
            $cookies[] = new Cookie('previewSize', $previewSize);
        } else {
            $previewSize = $request->cookies->get('previewSize');
        }
        if (!$previewSize) {
            // Default Preview Size.
            $previewSize = 'AutoWidth';
        }

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();
        $edit = true;

        // Get Preview User.
        $previewUserId = (int) $request->get('user');
        if ($previewUserId && $user->getId() !== $previewUserId) {
            $edit = false;
            /**
             * @var UserRead $user
             */
            $user = $em->getRepository(UserRead::class)->find($previewUserId);
        }

        /**
         * @var int $id PageStreamRead Id.
         */
        $id = $request->get('id');

        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        // Check if user has permission to edit this page template.
        $template = $pageStreamRead->getTemplate();
        $editPermission = $this->config['page_templates'][$template]['permissions']['edit'] ?? null;
        if (null !== $editPermission) {
            $this->denyAccessUnlessGranted($editPermission);
        }

        $response = $this->render('@cms/Admin/Page/edit.html.twig', [
            'pageStreamRead' => $pageStreamRead,
            'user' => $user,
            'edit' => $edit,
            'previewSize' => $previewSize,
        ]);

        // Set Settings Cookies.
        if (!empty($cookies)) {
            foreach ($cookies as $cookie) {
                if ($cookie instanceof Cookie) {
                    $response->headers->setCookie($cookie);
                }
            }
        }

        return $response;
    }

    /**
     * Get an array of page events grouped by user > page > events.
     *
     * @param EntityManagerInterface $em
     * @param int                    $currentWebsite
     *
     * @return array
     */
    private function getPageEvents(EntityManagerInterface $em, int $currentWebsite): array
    {
        /**
         * Get recent page submit events.
         *
         * @var EventStreamObject[]|null $latestCommits
         */
        $events = $em->getRepository(EventStreamObject::class)->findBy([
            'event' => PageSubmitEvent::class,
        ], [
            'id' => Criteria::DESC,
        ], 15);

        // Get list of page UUIDs.
        $pageUuids = array_map(static function (EventStreamObject $event) {
            return $event->getUuid();
        }, $events);

        /**
         * Get the pages.
         *
         * @var PageStreamRead[]|null $pages
         */
        $pages = $em->getRepository(PageStreamRead::class)->findBy([
            'uuid' => $pageUuids,
        ]);

        // Create a list of pages by UUID.
        $pagesByUuid = [];
        foreach ($pages as $page) {
            $pagesByUuid[$page->getUuid()] = $page;
        }

        // Build a multidimensional array of events grouped by user and page.
        $groupedEvents = [];
        foreach ($events as $event) {
            $user = $event->getUser();
            $aggregateUuid = $event->getUuid();

            /**
             * Check if page is visible on current website.
             *
             * @var PageStreamRead|null $page
             */
            $page = $pagesByUuid[$aggregateUuid] ?? null;
            $visible = null !== $page && $page->getWebsite() === $currentWebsite;

            if ($visible) {
                // Add the user group if it does not already exist.
                if (!isset($groupedEvents[$user])) {
                    $groupedEvents[$user] = [];
                }
                // Add the page group if it does not already exist.
                if (!isset($groupedEvents[$user][$aggregateUuid])) {
                    $groupedEvents[$user][$aggregateUuid] = [];
                }
                // Add the event.
                $groupedEvents[$user][$aggregateUuid][] = $event;
            }
        }

        return $groupedEvents;
    }

    /**
     * Get an array of events grouped by user > aggregate > events.
     *
     * @param EntityManagerInterface $em
     *
     * @return array
     */
    private function getNonePageEvents(EntityManagerInterface $em): array
    {
        $queryBuilder = $em->createQueryBuilder();
        $queryBuilder
            ->select('e')
            ->from('CQRSBundle:EventStreamObject', 'e')
            ->where($queryBuilder->expr()->neq('e.aggregateClass', ':pageClass'))
            ->orderBy('e.id', Criteria::DESC)
            ->setMaxResults(10)
            ->setParameter('pageClass', Page::class);

        $query = $queryBuilder->getQuery();
        /**
         * @var EventStreamObject[]|null $events
         */
        $events = $query->getResult();

        // Build a multidimensional array of events grouped by user and aggregate.
        $groupedEvents = [];
        foreach ($events as $event) {
            $user = $event->getUser();
            $aggregateUuid = $event->getUuid();

            // Add the user group if it does not already exist.
            if (!isset($groupedEvents[$user])) {
                $groupedEvents[$user] = [];
            }
            // Add the aggregate group if it does not already exist.
            if (!isset($groupedEvents[$user][$aggregateUuid])) {
                $groupedEvents[$user][$aggregateUuid] = [];
            }
            // Add the event.
            $groupedEvents[$user][$aggregateUuid][] = $event;
        }

        return $groupedEvents;
    }
}
