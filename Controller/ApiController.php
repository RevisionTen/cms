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

        $canSubmitChanges = $this->isGranted('page_submit_changes') && ($page->getVersion() !== $page->getStreamVersion());
        $canToggleContrast = !$previewUser;
        $canToggleTree = !$previewUser;
        $canPreview = !$previewUser;
        $canChangePagesettings = !$previewUser;
        $canRollbackAggregate = !$previewUser;
        $canOptimize = !$previewUser && $page->shouldTakeSnapshot();
        $canPublish = $this->isGranted('page_publish') && ((!$previewUser && $page->getVersion() === $page->getStreamVersion()) && (null === $publishedPage || null === $publishedPage->getVersion() || $page->getVersion() > $publishedPage->getVersion() + 1));
        $canUnpublish = $this->isGranted('page_unpublish') && (!$canSubmitChanges && !$previewUser && null !== $publishedPage && $page->published && ($page->getVersion() === $publishedPage->getVersion() + 1 || $page->getVersion() === $publishedPage->getVersion()));
        $canUndoChange = !$previewUser && $page->getVersion() !== $page->getStreamVersion();
        $canDiscardChanges = !$previewUser && $page->getVersion() !== $page->getStreamVersion();
        $canSchedule = $this->isGranted('page_schedule') && ($canPublish || $canUnpublish);
        $canInspect = $this->isGranted('page_inspect');
        $canCloneAggregate = $this->isGranted('page_clone') && $this->isGrantedTemplatePermission('new', $page->template) && (!$previewUser && !$pageStreamRead->getDeleted());
        $canDeleteAggregate = $this->isGranted('page_delete') && $this->isGrantedTemplatePermission('delete', $page->template) && (!$previewUser && !$pageStreamRead->getDeleted());

        $actions = [
            'toggle_contrast' => [
                'css_class' => 'btn-tertiary toggle-contrast',
                'icon' => 'fas fa-adjust',
                'label' => $translator->trans('admin.btn.toggleContrast', [], 'cms'),
                'url' => '#',
                'display' => $canToggleContrast,
                'type' => 'link',
            ],
            'toggle_tree' => [
                'css_class' => 'btn-tertiary toggle-tree',
                'icon' => 'fas fa-layer-group',
                'label' => $translator->trans('admin.btn.showLayers', [], 'cms'),
                'url' => '#',
                'display' => $canToggleTree,
                'type' => 'link',
            ],
            'preview' => [
                'css_class' => 'btn-tertiary toggle-editor',
                'icon' => 'fas fa-toggle-on',
                'label' => $translator->trans('admin.btn.preview', [], 'cms'),
                'url' => $this->generateUrl('cms_page_preview', ['pageUuid' => $pageUuid]),
                'display' => $canPreview,
                'type' => 'link',
                'attributes' => [
                    'target' => '_blank',
                ],
            ],
            'change_pagesettings' => [
                'css_class' => 'btn-tertiary',
                'icon' => 'fa fa-edit',
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
                'css_class' => 'btn-tertiary',
                'icon' => 'fas fa-sync',
                'label' => $translator->trans('admin.btn.optimize', [], 'cms'),
                'url' => $this->generateUrl('cms_save_snapshot', ['pageUuid' => $pageUuid]),
                'display' => $canOptimize,
                'type' => 'ajax',
            ],
            'inspect' => [
                'css_class' => 'btn-tertiary',
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
                'icon' => 'fa fa-trash',
                'label' => $translator->trans('admin.btn.discardChanges', [], 'cms'),
                'url' => $this->generateUrl('cms_discard_changes', ['pageUuid' => $pageUuid]),
                'display' => $canDiscardChanges,
                'type' => 'link',
            ],
            'clone_aggregate' => [
                'css_class' => 'btn-tertiary',
                'icon' => 'fa fa-clone',
                'label' => $translator->trans('admin.btn.clonePage', [], 'cms'),
                'url' => $this->generateUrl('cms_clone_aggregate', ['id' => $pageStreamRead->getId()]),
                'display' => $canCloneAggregate,
                'type' => 'link',
                'attributes' => [
                    'onclick' => 'return confirm(\''.$translator->trans('admin.label.confirmDuplicate', [], 'cms').'\')',
                ],
            ],
            'delete_aggregate' => [
                'css_class' => 'btn-tertiary',
                'icon' => 'fa fa-trash',
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

        $data['html'] = $this->render('@cms/Admin/Page/toolbar.twig', $dataInfo)->getContent();

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

        return $this->render('@cms/Admin/Page/Tree/tree.html.twig', [
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
