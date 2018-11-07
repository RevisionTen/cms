<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\CmsBundle;
use RevisionTen\CMS\Event\PageSubmitEvent;
use RevisionTen\CMS\Model\PageStreamRead;
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
     * @param int $id
     *
     * @return Response
     */
    public function websiteTitle(EntityManagerInterface $entityManager, int $id): Response
    {
        /** @var Website $website */
        $website = $entityManager->getRepository(Website::class)->find($id);

        return $this->render('@cms/Admin/website_info.html.twig', [
            'website' => $website ?? [
                'id' => '0',
                'title' => 'unknown',
            ],
        ]);
    }

    /**
     * Get the username from the user id.
     *
     * @param int    $userId
     * @param string $template
     *
     * @return Response
     */
    public function userName(EntityManagerInterface $entityManager, int $userId, string $template = '@cms/Admin/user_info.html.twig'): Response
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
     * @param string $uuid
     *
     * @return Response
     */
    public function uuidTitle(string $uuid): Response
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

        if ($pageStreamRead) {
            $title = $pageStreamRead->getTitle();
        } elseif ($formRead) {
            $title = $formRead->getTitle();
        } elseif ($userRead) {
            $title = $userRead->getUsername();
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
        /** @var EventStreamObject[]|null $eventStreamObjects */
        $eventStreamObjects = $em->getRepository(EventStreamObject::class)->findby([], ['id' => Criteria::DESC], 6);

        /** @var EventQeueObject[]|null $eventQeueObjects */
        $eventQeueObjects = $em->getRepository(EventQeueObject::class)->findby([], ['id' => Criteria::DESC], 6);

        /** @var EventStreamObject[]|null $latestCommits */
        $latestCommits = $em->getRepository(EventStreamObject::class)->findby([
            'event' => PageSubmitEvent::class,
        ], ['id' => Criteria::DESC], 7);

        return $this->render('@cms/Admin/dashboard.html.twig', [
            'eventStreamObjects' => $eventStreamObjects,
            'eventQeueObjects' => $eventQeueObjects,
            'latestCommits' => $latestCommits,
            'symfony_version' => Kernel::VERSION,
            'cms_version' => CmsBundle::VERSION,
            'php_version' => phpversion(),
            'apc_enabled' => (extension_loaded('apcu') && ini_get('apc.enabled') && function_exists('apcu_clear_cache')) ? 'enabled' : 'disabled',
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
        $cookies = [];
        if ($previewSize = $request->get('previewSize')) {
            $cookies[] = new Cookie('previewSize', $previewSize);
        } else {
            $previewSize = $request->cookies->get('previewSize');
        }

        // Default Preview Size.
        if (!$previewSize) {
            $previewSize = 'AutoWidth';
        }

        /** @var UserRead $user */
        $user = $this->getUser();
        $edit = true;

        // Get Preview User.
        if ($previewUserId = $request->get('user')) {
            if ($user->getId() != $previewUserId) {
                $edit = false;
                /** @var UserRead $user */
                $user = $em->getRepository(UserRead::class)->find($previewUserId);
            }
        }

        /** @var int $id PageStreamRead Id. */
        $id = $request->get('id');

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        $response = $this->render('@cms/Admin/edit-aggregate.html.twig', [
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
