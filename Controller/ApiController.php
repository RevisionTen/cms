<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\User;
use RevisionTen\CQRS\Services\AggregateFactory;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\EventStore;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class ApiController.
 *
 * @Route("/admin/api")
 */
class ApiController extends Controller
{
    /**
     * @Route("/page-info/{pageUuid}/{userId}", name="cms_api_page_info")
     *
     * @param string                 $pageUuid
     * @param EntityManagerInterface $em
     * @param AggregateFactory       $aggregateFactory
     * @param TranslatorInterface    $translator
     * @param EventStore             $eventStore
     *
     * @return JsonResponse
     */
    public function getPageInfo(string $pageUuid, int $userId = null, EntityManagerInterface $em, AggregateFactory $aggregateFactory, TranslatorInterface $translator, EventStore $eventStore): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $previewUser = false;

        if ($userId !== $user->getId()) {
            $previewUser = true;
            /**
             * Load Preview User.
             *
             * @var User $user
             */
            $user = $em->getRepository(User::class)->find($userId);
            if (null === $user) {
                return new JsonResponse(false, 404);
            }
        }

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);

        if (null === $pageStreamRead) {
            return new JsonResponse(false, 404);
        }

        /** @var Page $page */
        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());

        /** @var PageRead $publishedPage */
        $publishedPage = $em->getRepository(PageRead::class)->findOneByUuid($pageUuid);

        $actions = [
            'show' => [
                'css_class' => ' btn-tertiary',
                'icon' => 'fas fa-eye',
                'label' => $translator->trans('View'),
                'url' => $this->generateUrl('cms_page_show', ['pageUuid' => $pageUuid]),
                'display' => ($previewUser === false),
                'type' => 'link',
            ],
            'change_pagesettings' => [
                'css_class' => 'info btn-tertiary',
                'icon' => 'fa fa-edit',
                'label' => $translator->trans('Change Page Settings'),
                'url' => $this->generateUrl('cms_change_pagesettings', ['pageUuid' => $pageUuid, 'version' => $page->getVersion()]),
                'display' => ($previewUser === false),
                'type' => 'form',
            ],
            'publish' => [
                'css_class' => 'success',
                'icon' => 'fas fa-bullhorn',
                'label' => $translator->trans('Publish'),
                'url' => $this->generateUrl('cms_publish_page', ['pageUuid' => $pageUuid, 'version' => $page->getStreamVersion()]),
                'display' => ($previewUser === false && $page->getVersion() === $page->getStreamVersion()) && (null === $publishedPage || null === $publishedPage->getVersion() || $page->getVersion() !== $publishedPage->getVersion() + 1),
                'type' => 'ajax',
            ],
            'unpublish' => [
                'css_class' => 'danger',
                'icon' => 'fas fa-eye-slash',
                'label' => $translator->trans('Unpublish'),
                'url' => $this->generateUrl('cms_unpublish_page', ['pageUuid' => $pageUuid]),
                'display' => ($previewUser === false && null !== $publishedPage && $page->getVersion() === $publishedPage->getVersion() + 1 && $page->published),
                'type' => 'ajax',
            ],
            'optimize' => [
                'css_class' => 'success btn-tertiary',
                'icon' => 'fas fa-sync',
                'label' => $translator->trans('Optimize'),
                'url' => $this->generateUrl('cms_save_snapshot', ['pageUuid' => $pageUuid]),
                'display' => ($previewUser === false && $page->shouldTakeSnapshot()),
                'type' => 'ajax',
            ],
            'undo_change' => [
                'css_class' => '',
                'icon' => 'fas fa-undo',
                'label' => $translator->trans('Undo last change'),
                'url' => $this->generateUrl('cms_undo_change', ['pageUuid' => $pageUuid, 'version' => $page->getVersion()]),
                'display' => ($previewUser === false && $page->getVersion() !== $page->getStreamVersion()),
                'type' => 'link',
            ],
            'submit_changes' => [
                'css_class' => 'success',
                'icon' => 'fas fa-check-circle',
                'label' => $translator->trans('Submit changes'),
                'url' => $this->generateUrl('cms_submit_changes', ['pageUuid' => $pageUuid, 'version' => $page->getVersion(), 'qeueUser' => $user->getId()]),
                'display' => ($page->getVersion() !== $page->getStreamVersion()),
                'type' => 'form',
            ],
            'rollback_aggregate' => [
                'css_class' => '',
                'icon' => 'fas fa-history',
                'label' => $translator->trans('Rollback'),
                'url' => $this->generateUrl('cms_rollback_aggregate', ['pageUuid' => $pageUuid, 'version' => $page->getVersion()]),
                'display' => ($previewUser === false),
                'type' => 'form',
            ],
            'discard_changes' => [
                'css_class' => 'danger',
                'icon' => 'fa fa-trash',
                'label' => $translator->trans('Discard changes'),
                'url' => $this->generateUrl('cms_discard_changes', ['pageUuid' => $pageUuid]),
                'display' => ($previewUser === false && $page->getVersion() !== $page->getStreamVersion()),
                'type' => 'link',
            ],
            'clone_aggregate' => [
                'css_class' => 'warning btn-tertiary',
                'icon' => 'fa fa-clone',
                'label' => $translator->trans('Clone page'),
                'url' => $this->generateUrl('cms_clone_aggregate', ['id' => $pageStreamRead->getId()]),
                'display' => ($previewUser === false && false === $pageStreamRead->getDeleted()),
                'type' => 'link',
            ],
            'delete_aggregate' => [
                'css_class' => 'danger btn-tertiary',
                'icon' => 'fa fa-trash',
                'label' => $translator->trans('Delete page'),
                'url' => $this->generateUrl('cms_delete_aggregate', ['id' => $pageStreamRead->getId()]),
                'display' => ($previewUser === false && false === $pageStreamRead->getDeleted()),
                'type' => 'link',
            ],
        ];

        $data = [
            'id' => $pageStreamRead->getId(),
            'uuid' => $page->getUuid(),
            'title' => $page->title,
            'version' => $page->getVersion(),
            'publishedVersion' => $publishedPage ? $publishedPage->getVersion() : null,
            'streamVersion' => $page->getStreamVersion(),
            'snapshotVersion' => $page->getSnapshotVersion(),
            'user_id' => $user->getId(),
            'actions' => $actions,
            'previewUser' => $previewUser,
        ];

        $users = [];
        if ($previewUser === false) {
            /** @var User[] $adminUsers */
            $adminUsers = $em->getRepository(User::class)->findAll();
            foreach ($adminUsers as $key => $adminUser) {
                if ($adminUser->getId() === $user->getId()) {
                    continue;
                }

                $eventStreamObjects = $eventStore->findQeued($pageStreamRead->getUuid(), null, $pageStreamRead->getVersion() + 1, $adminUser->getId());
                if ($eventStreamObjects) {
                    $users[$adminUser->getId()] = [
                        'events' => $eventStreamObjects,
                        'user' => $adminUser,
                    ];
                }
            }
        }
        $dataInfo = $data;
        $dataInfo['users'] = $users;
        $dataInfo['user'] = $user;
        $dataInfo['aliases'] = $pageStreamRead->getAliases();

        $data['html'] = $this->render('@cms/Admin/page-info.html.twig', $dataInfo)->getContent();

        return new JsonResponse($data);
    }
}
