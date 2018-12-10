<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\CmsBundle;
use RevisionTen\CMS\Event\PageSubmitEvent;
use RevisionTen\CMS\Model\MenuRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\RoleRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CQRS\Model\EventQeueObject;
use RevisionTen\CQRS\Model\EventStreamObject;
use RevisionTen\Forms\Model\FormRead;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class AdminController.
 *
 * @Route("/admin")
 */
class AdminController extends AbstractController
{

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
        /** @var Website $website */
        $website = $entityManager->getRepository(Website::class)->find($id);

        return $this->render('@cms/Admin/Website/website_info.html.twig', [
            'website' => $website ?? [
                'id' => '0',
                'title' => 'unknown',
            ],
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
            /** @var UserRead $user */
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

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var PageStreamRead|null $pageStreamRead */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->findOneByUuid($uuid);
        /** @var FormRead|null $formRead */
        $formRead = $em->getRepository(FormRead::class)->findOneByUuid($uuid);
        /** @var UserRead|null $userRead */
        $userRead = $em->getRepository(UserRead::class)->findOneByUuid($uuid);
        /** @var MenuRead|null $menuRead */
        $menuRead = $em->getRepository(MenuRead::class)->findOneByUuid($uuid);
        /** @var FileRead|null $fileRead */
        $fileRead = $em->getRepository(RoleRead::class)->findOneByUuid($uuid);
        /** @var RoleRead|null $roleRead */
        $roleRead = $em->getRepository(RoleRead::class)->findOneByUuid($uuid);

        if ($pageStreamRead) {
            $title = $pageStreamRead->getTitle();
        } elseif ($formRead) {
            $title = $formRead->getTitle();
        } elseif ($userRead) {
            $title = $userRead->getUsername();
        } elseif ($menuRead) {
            $title = $translator->trans($menuRead->getTitle());
        } elseif ($fileRead) {
            $title = $translator->trans($fileRead->getTitle());
        } elseif ($roleRead) {
            $title = $translator->trans($roleRead->getTitle());
        }

        return new Response($title);
    }

    /**
     * @Route("", name="cms_admin")
     * @Route("/", name="cms_admin_slash")
     * @Route("/dashboard", name="cms_dashboard")
     *
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function dashboardAction(EntityManagerInterface $em): Response
    {
        // Test If the cache is enabled.
        $shm_key = $this->getParameter('cms')['shm_key'] ?? 'none';
        if ('none' !== $shm_key) {
            if (function_exists('shm_attach')) {
                try {
                    // Create a 1MB shared memory segment for the UuidStore.
                    $shmSegment = shm_attach($shm_key, 1000000, 0666);
                } catch (\Exception $exception) {
                    $shmSegment = false;
                }
                $shm_enabled = $shmSegment ? true : false;
            } else {
                $shm_enabled = false;
            }
        } else {
            $shm_enabled = true;
        }

        /** @var EventStreamObject[]|null $eventStreamObjects */
        $eventStreamObjects = $em->getRepository(EventStreamObject::class)->findBy([], ['id' => Criteria::DESC], 6);

        /** @var EventQeueObject[]|null $eventQeueObjects */
        $eventQeueObjects = $em->getRepository(EventQeueObject::class)->findBy([], ['id' => Criteria::DESC], 6);

        /** @var EventStreamObject[]|null $latestCommits */
        $latestCommits = $em->getRepository(EventStreamObject::class)->findBy([
            'event' => PageSubmitEvent::class,
        ], ['id' => Criteria::DESC], 7);



        return $this->render('@cms/Admin/dashboard.html.twig', [
            'shm_enabled' => $shm_enabled,
            'shm_key' => $shm_key,
            'eventStreamObjects' => $this->groupEventsByUser($eventStreamObjects),
            'eventQeueObjects' => $this->groupEventsByUser($eventQeueObjects),
            'latestCommits' => $this->groupEventsByUser($latestCommits),
            'symfony_version' => Kernel::VERSION,
            'cms_version' => CmsBundle::VERSION,
            'php_version' => PHP_VERSION,
            'apc_enabled' => (\extension_loaded('apcu') && ini_get('apc.enabled') && \function_exists('apcu_clear_cache')) ? 'enabled' : 'disabled',
            'memory_limit' => ini_get('memory_limit'),
            'upload_limit' => ini_get('upload_max_filesize'),
            'post_limit' => ini_get('post_max_size'),
            'execution_limit' => ini_get('max_execution_time'),
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']),
            'database_name' => $em->getConnection()->getDatabase(),
        ]);
    }

    private function groupEventsByUser(array $events = null): array
    {
        $grouped = [];
        foreach ($events as $event) {
            $eventUser = $event->getUser();
            if (!isset($grouped[$eventUser])) {
                $grouped[$eventUser] = [];
            }
            $grouped[$eventUser][] = $event;
        }

        return $grouped;
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

        /** @var UserRead $user */
        $user = $this->getUser();
        $edit = true;

        // Get Preview User.
        $previewUserId = (int) $request->get('user');
        if ($previewUserId && $user->getId() !== $previewUserId) {
            $edit = false;
            /** @var UserRead $user */
            $user = $em->getRepository(UserRead::class)->find($previewUserId);
        }

        /** @var int $id PageStreamRead Id. */
        $id = $request->get('id');

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
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
}
