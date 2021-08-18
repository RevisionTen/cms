<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CQRS\Services\AggregateFactory;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\EventStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ApiController.
 *
 * @Route("/admin/api")
 */
class ApiController extends AbstractController
{
    /**
     * @Route("/page-info/{pageUuid}/{userId}", name="cms_api_page_info")
     *
     * @param Request                $request
     * @param EntityManagerInterface $entityManager
     * @param AggregateFactory       $aggregateFactory
     * @param TranslatorInterface    $translator
     * @param EventStore             $eventStore
     * @param string                 $pageUuid
     * @param int                    $userId
     *
     * @return JsonResponse
     */
    public function getPageInfo(Request $request, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, TranslatorInterface $translator, EventStore $eventStore, string $pageUuid, int $userId = null): JsonResponse
    {
        $this->denyAccessUnlessGranted('page_edit');

        $user = $this->getApiUser($userId, $entityManager);

        if (null === $user) {
            return new JsonResponse(false, 404);
        }
        $previewUser = $user->isImposter();

        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);

        if (null === $pageStreamRead) {
            return new JsonResponse(false, 404);
        }

        $currentWebsite = $request->get('currentWebsite');
        if ($currentWebsite && $pageStreamRead->getWebsite() !== $currentWebsite) {
            throw new AccessDeniedHttpException('Page does not exist on this website');
        }

        /**
         * @var Page $page
         */
        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());

        /**
         * @var PageRead $publishedPage
         */
        $publishedPage = $entityManager->getRepository(PageRead::class)->findOneBy([ 'uuid' => $pageUuid ]);

        // Get Preview Size.
        $previewSize = $request->get('previewSize');
        if (null === $previewSize) {
            $previewSize = $request->cookies->get('previewSize');
        }
        if (null === $previewSize) {
            // Default Preview Size.
            $previewSize = 'AutoWidth';
        }

        // Pages can also be published if the state is not in the list of predefined states.
        #$canBePublished = $page->state === Page::STATE_STAGED || $page->state === Page::STATE_UNPUBLISHED;
        $canBePublished = !in_array($page->state, [
            Page::STATE_PUBLISHED,
            Page::STATE_SCHEDULED,
            Page::STATE_SCHEDULED_UNPUBLISH,
            Page::STATE_DELETED,
            Page::STATE_DRAFT,
        ], true);

        $canBeUnpublished = $page->state === Page::STATE_PUBLISHED;

        $canSubmitChanges = !$page->locked && $this->isGranted('page_submit_changes') && ($page->getVersion() !== $page->getStreamVersion());
        $canToggleContrast = !$page->locked && !$previewUser;
        $canToggleTree = !$page->locked && !$previewUser;
        $canPreview = !$page->locked && !$previewUser;
        $canUseSpacingTool = !$page->locked && !$previewUser;
        $canChangePagesettings = !$page->locked && !$previewUser;
        $canRollbackAggregate = !$page->locked && !$previewUser;
        $canOptimize = !$page->locked && !$previewUser && $page->shouldTakeSnapshot();
        $canPublish = !$page->locked && !$previewUser && $this->isGranted('page_publish') && $canBePublished;
        $canUnpublish = !$page->locked && !$previewUser && $this->isGranted('page_unpublish') && $canBeUnpublished;
        $canUndoChange = !$page->locked && !$previewUser && $page->getVersion() !== $page->getStreamVersion();
        $canDiscardChanges = !$page->locked && !$previewUser && $page->getVersion() !== $page->getStreamVersion();
        $canSchedule = !$page->locked && !$previewUser && $this->isGranted('page_schedule') && ($this->isGranted('page_publish') || $this->isGranted('page_unpublish'));
        $canInspect = !$previewUser && $this->isGranted('page_inspect');
        $canLockUnlock = !$previewUser && $this->isGranted('page_lock_unlock');
        $canLock = $canLockUnlock && !$page->locked;
        $canUnlock = $canLockUnlock && $page->locked;
        $canCloneAggregate = !$page->locked && $this->isGranted('page_clone') && $this->isGrantedTemplatePermission('new', $page->template) && (!$previewUser && !$pageStreamRead->getDeleted());
        $canDeleteAggregate = !$page->locked && $this->isGranted('page_delete') && $this->isGrantedTemplatePermission('delete', $page->template) && (!$previewUser && !$pageStreamRead->getDeleted());

        $actions = [
            'toggle_contrast' => [
                'css_class' => 'toggle-contrast',
                'icon' => 'fas fa-adjust',
                'label' => $translator->trans('admin.btn.toggleContrast', [], 'cms'),
                'url' => '#',
                'display' => $canToggleContrast,
                'type' => 'link',
            ],
            'toggle_tree' => [
                'css_class' => 'toggle-tree',
                'icon' => 'fas fa-layer-group',
                'label' => $translator->trans('admin.btn.showLayers', [], 'cms'),
                'url' => '#',
                'display' => $canToggleTree,
                'type' => 'link',
            ],
            'contentEditor' => [
                'css_class' => 'toggle-view-mode active',
                'icon' => 'fas fa-pencil-alt',
                'label' => $translator->trans('admin.btn.contentEditor', [], 'cms'),
                'url' => '#',
                'display' => $canPreview || $canUseSpacingTool,
                'type' => 'link',
                'attributes' => [
                    'data-mode' => 'editor',
                ],
            ],
            'spacing_tool' => [
                'css_class' => 'toggle-view-mode',
                'icon' => 'fas fa-ruler-combined',
                'label' => $translator->trans('admin.btn.spacingTool', [], 'cms'),
                'url' => '#',
                'display' => $canUseSpacingTool,
                'type' => 'link',
                'attributes' => [
                    'data-mode' => 'spacing',
                ],
            ],
            'preview' => [
                'css_class' => 'toggle-view-mode',
                'icon' => 'fas fa-eye',
                'label' => $translator->trans('admin.btn.preview', [], 'cms'),
                'url' => '#',
                'display' => $canPreview,
                'type' => 'link',
                'attributes' => [
                    'data-mode' => 'preview',
                ],
            ],
            'change_pagesettings' => [
                'css_class' => '',
                'icon' => 'fas fa-cogs',
                'label' => $translator->trans('admin.btn.changePageSettings', [], 'cms'),
                'url' => $this->generateUrl('cms_change_pagesettings', ['pageUuid' => $pageUuid, 'version' => $page->getVersion()]),
                'display' => $canChangePagesettings,
                'type' => 'tab',
            ],
            'publish' => [
                'css_class' => 'btn-success',
                'icon' => 'fas fa-bullhorn',
                'label' => $translator->trans('admin.btn.publish', [], 'cms'),
                'url' => $this->generateUrl('cms_publish_page', ['pageUuid' => $pageUuid, 'version' => $page->getStreamVersion()]),
                'display' => $canPublish,
                'type' => 'ajax',
            ],
            'unpublish' => [
                'css_class' => 'btn-danger',
                'icon' => 'fas fa-eye-slash',
                'label' => $translator->trans('admin.btn.unpublish', [], 'cms'),
                'url' => $this->generateUrl('cms_unpublish_page', ['pageUuid' => $pageUuid]),
                'display' => $canUnpublish,
                'type' => 'ajax',
            ],
            'schedule' => [
                'css_class' => '',
                'icon' => 'fas fa-clock',
                'label' => $translator->trans('admin.btn.schedule', [], 'cms'),
                'url' => $this->generateUrl('cms_schedule_page', ['pageUuid' => $pageUuid, 'version' => $page->getVersion()]),
                'display' => $canSchedule,
                'type' => 'form',
            ],
            'optimize' => [
                'css_class' => '',
                'icon' => 'fas fa-sync',
                'label' => $translator->trans('admin.btn.optimize', [], 'cms'),
                'url' => $this->generateUrl('cms_save_snapshot', ['pageUuid' => $pageUuid]),
                'display' => $canOptimize,
                'type' => 'ajax',
            ],
            'inspect' => [
                'css_class' => '',
                'icon' => 'fas fa-microscope',
                'label' => $translator->trans('admin.btn.inspect', [], 'cms'),
                'url' => $this->generateUrl('cms_inspect_page', ['pageUuid' => $pageUuid]),
                'display' => $canInspect,
                'type' => 'form',
            ],
            'undo_change' => [
                'css_class' => '',
                'icon' => 'fas fa-undo',
                'label' => $translator->trans('admin.btn.undoLastChange', [], 'cms'),
                'url' => $this->generateUrl('cms_undo_change', ['pageUuid' => $pageUuid, 'version' => $page->getVersion()]),
                'display' => $canUndoChange,
                'type' => 'link',
            ],
            'submit_changes' => [
                'css_class' => 'btn-success',
                'icon' => 'fas fa-check-circle',
                'label' => $translator->trans('admin.btn.submitChanges', [], 'cms'),
                'url' => $this->generateUrl('cms_submit_changes', ['pageUuid' => $pageUuid, 'version' => $page->getVersion(), 'qeueUser' => $user->getId()]),
                'display' => $canSubmitChanges,
                'type' => 'form',
            ],
            'rollback_aggregate' => [
                'css_class' => '',
                'icon' => 'fas fa-history',
                'label' => $translator->trans('admin.btn.rollback', [], 'cms'),
                'url' => $this->generateUrl('cms_rollback_aggregate', ['pageUuid' => $pageUuid, 'version' => $page->getVersion()]),
                'display' => $canRollbackAggregate,
                'type' => 'form',
            ],
            'discard_changes' => [
                'css_class' => 'btn-danger',
                'icon' => 'fas fa-trash',
                'label' => $translator->trans('admin.btn.discardChanges', [], 'cms'),
                'url' => $this->generateUrl('cms_discard_changes', ['pageUuid' => $pageUuid]),
                'display' => $canDiscardChanges,
                'type' => 'link',
            ],
            'lock' => [
                'css_class' => '',
                'icon' => 'fas fa-unlock',
                'label' => $translator->trans('admin.btn.lockPage', [], 'cms'),
                'url' => $this->generateUrl('cms_lock_page', ['id' => $pageStreamRead->getId()]),
                'display' => $canLock,
                'type' => 'link',
                'attributes' => [
                    'onclick' => 'return confirm(\''.$translator->trans('admin.label.confirmLock', [], 'cms').'\')',
                ],
            ],
            'unlock' => [
                'css_class' => '',
                'icon' => 'fas fa-lock',
                'label' => $translator->trans('admin.btn.unlockPage', [], 'cms'),
                'url' => $this->generateUrl('cms_unlock_page', ['id' => $pageStreamRead->getId()]),
                'display' => $canUnlock,
                'type' => 'link',
                'attributes' => [
                    'onclick' => 'return confirm(\''.$translator->trans('admin.label.confirmUnlock', [], 'cms').'\')',
                ],
            ],
            'clone_aggregate' => [
                'css_class' => '',
                'icon' => 'fas fa-clone',
                'label' => $translator->trans('admin.btn.clonePage', [], 'cms'),
                'url' => $this->generateUrl('cms_clone_aggregate', ['id' => $pageStreamRead->getId()]),
                'display' => $canCloneAggregate,
                'type' => 'link',
                'attributes' => [
                    'onclick' => 'return confirm(\''.$translator->trans('admin.label.confirmDuplicate', [], 'cms').'\')',
                ],
            ],
            'delete_aggregate' => [
                'css_class' => 'text-danger',
                'icon' => 'fas fa-trash',
                'label' => $translator->trans('admin.btn.deletePage', [], 'cms'),
                'url' => $this->generateUrl('cms_delete_aggregate', ['id' => $pageStreamRead->getId()]),
                'display' => $canDeleteAggregate,
                'type' => 'link',
                'attributes' => [
                    'onclick' => 'return confirm(\''.$translator->trans('admin.label.confirmDelete', [], 'cms').'\')',
                ],
            ],
        ];

        $state = $pageStreamRead->getState();
        if (null === $state) {
            $state = $pageStreamRead->isPublished() ? Page::STATE_PUBLISHED : Page::STATE_UNPUBLISHED;
        }

        $data = [
            'id' => $pageStreamRead->getId(),
            'state' => $state,
            'schedule' => $page->schedule,
            'uuid' => $page->getUuid(),
            'title' => $page->title,
            'version' => $page->getVersion(),
            'publishedVersion' => $publishedPage ? $publishedPage->getVersion() : null,
            'streamVersion' => $page->getStreamVersion(),
            'snapshotVersion' => $page->getSnapshotVersion(),
            'user_id' => $user->getId(),
            'actions' => $actions,
            'previewUser' => $previewUser,
            'previewSize' => $previewSize,
        ];

        $users = [];
        if (false === $previewUser) {
            /**
             * @var UserRead[] $adminUsers
             */
            $adminUsers = $entityManager->getRepository(UserRead::class)->findAll();
            foreach ($adminUsers as $key => $adminUser) {
                if ($adminUser->getId() === $user->getId()) {
                    continue;
                }

                $eventStreamObjects = $eventStore->findQueued($pageStreamRead->getUuid(), $adminUser->getId(), null, $pageStreamRead->getVersion() + 1);
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

        $data['html'] = $this->render('@CMS/Backend/Page/toolbar.twig', $dataInfo)->getContent();

        return new JsonResponse($data);
    }

    /**
     * @Route("/page-tree/{pageUuid}/{userId}", name="cms_api_page_tree")
     *
     * @param string                 $pageUuid
     * @param int|null               $userId
     * @param AggregateFactory       $aggregateFactory
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     */
    public function getPageTree(string $pageUuid, int $userId, AggregateFactory $aggregateFactory, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('page_edit');

        $user = $this->getApiUser($userId, $entityManager);
        if (null === $user) {
            return new JsonResponse(false, 404);
        }

        /**
         * @var Page $page
         */
        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());

        $config = $this->getParameter('cms');

        return $this->render('@CMS/Backend/Page/Tree/tree.html.twig', [
            'pageUuid' => $pageUuid,
            'onVersion' => $page->getVersion(),
            'tree' => $this->getChildren($config, $page->elements),
            'config' => $config,
        ]);
    }

    /**
     * @param int                    $userId
     * @param EntityManagerInterface $entityManager
     *
     * @return UserRead|null
     */
    private function getApiUser(int $userId, EntityManagerInterface $entityManager): ?UserRead
    {
        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $imposter = ($userId !== $user->getId());

        if ($imposter) {
            /**
             * Load Preview User.
             *
             * @var UserRead $user
             */
            $user = $entityManager->getRepository(UserRead::class)->find($userId);
            $user->setImposter(true);
        }

        return $user;
    }

    /**
     *
     * @param array      $config
     * @param array|null $elements
     *
     * @return array
     */
    private function getChildren(array $config, $elements = null): array
    {
        $children = [];

        if ($elements) {
            foreach ($elements as $element) {
                $children[] = [
                    'elementName' => $element['elementName'],
                    'title' => 'Section' === $element['elementName'] ? $element['data']['section'] : '',
                    'uuid' => $element['uuid'],
                    'elements' => isset($element['elements']) ? $this->getChildren($config, $element['elements']) : [],
                    'supportChildTypes' => $config['page_elements'][$element['elementName']]['children'] ?? [],
                ];
            }
        }

        return $children;
    }

    /**
     * Checks if the user has access to a provided page template.
     *
     * @param string $permissionName
     * @param string $template
     *
     * @return bool
     */
    private function isGrantedTemplatePermission(string $permissionName, string $template): bool
    {
        $config = $this->getParameter('cms');
        $permission = $config['page_templates'][$template]['permissions'][$permissionName] ?? null;

        if (null === $permission) {
            // Permission is not explicitly set, grant access.
            return true;
        }

        return $this->isGranted($permission);
    }
}
