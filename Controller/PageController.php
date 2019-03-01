<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use RevisionTen\CMS\Command\PageAddElementCommand;
use RevisionTen\CMS\Command\PageCloneCommand;
use RevisionTen\CMS\Command\PageDeleteCommand;
use RevisionTen\CMS\Command\PageDisableElementCommand;
use RevisionTen\CMS\Command\PageDuplicateElementCommand;
use RevisionTen\CMS\Command\PageEditElementCommand;
use RevisionTen\CMS\Command\PageEnableElementCommand;
use RevisionTen\CMS\Command\PagePublishCommand;
use RevisionTen\CMS\Command\PageRemoveElementCommand;
use RevisionTen\CMS\Command\PageResizeColumnCommand;
use RevisionTen\CMS\Command\PageRollbackCommand;
use RevisionTen\CMS\Command\PageSaveOrderCommand;
use RevisionTen\CMS\Command\PageShiftElementCommand;
use RevisionTen\CMS\Command\PageSubmitCommand;
use RevisionTen\CMS\Command\PageUnpublishCommand;
use RevisionTen\CMS\Form\ElementType;
use RevisionTen\CMS\Form\PageType;
use RevisionTen\CMS\Handler\PageBaseHandler;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Command\PageChangeSettingsCommand;
use RevisionTen\CMS\Command\PageCreateCommand;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\Utilities\ArrayHelpers;
use RevisionTen\CQRS\Exception\InterfaceException;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\EventStore;
use RevisionTen\CQRS\Services\MessageBus;
use RevisionTen\CQRS\Services\SnapshotStore;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Cocur\Slugify\Slugify;

/**
 * Class PageController.
 *
 * @Route("/admin")
 */
class PageController extends AbstractController
{
    /** @var MessageBus */
    private $messageBus;

    public function __construct(MessageBus $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    private function getToolbarRefreshHeaders(): array
    {
        $headers = [];
        if ('dev' === $this->getParameter('kernel.environment')) {
            $headers['Symfony-Debug-Toolbar-Replace'] = 1;
        }

        return $headers;
    }

    /**
     * A wrapper function to execute a Command.
     * Returns true if the command succeeds.
     *
     * @param CommandBus  $commandBus
     * @param string      $commandClass
     * @param array       $data
     * @param string      $aggregateUuid
     * @param int         $onVersion
     * @param boolean     $qeue
     * @param string|null $commandUuid
     * @param int|null    $userId
     *
     * @return bool
     */
    public function runCommand(CommandBus $commandBus, string $commandClass, array $data, string $aggregateUuid, int $onVersion, bool $qeue = false, string $commandUuid = null, int $userId = null): bool
    {
        if (null === $userId) {
            /** @var UserRead $user */
            $user = $this->getUser();
            $userId = $user->getId();
        }

        $success = false;
        $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };

        $command = new $commandClass($userId, $commandUuid, $aggregateUuid, $onVersion, $data, $successCallback);

        $commandBus->dispatch($command, $qeue);

        return $success;
    }

    /**
     * Returns info from the messageBus.
     *
     * @return JsonResponse
     */
    public function errorResponse(): JsonResponse
    {
        return new JsonResponse($this->messageBus->getMessagesJson());
    }

    /**
     * Displays the Page Aggregate create form.
     *
     * @Route("/create-page", name="cms_create_page")
     *
     * @param Request                $request
     * @param CommandBus             $commandBus
     * @param TranslatorInterface    $translator
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function createPage(Request $request, CommandBus $commandBus, TranslatorInterface $translator)
    {
        $this->denyAccessUnlessGranted('page_create');

        /** @var UserRead $user */
        $user = $this->getUser();
        $config = $this->getParameter('cms');
        $ignore_validation = $request->get('ignore_validation');
        $currentWebsite = (int) $request->get('currentWebsite');

        $data = [];
        $data['language'] = $request->getLocale();
        $pageWebsites = [];
        /** @var Website[] $websites */
        $websites = $websites = $user->getWebsites();
        foreach ($websites as $website) {
            $pageWebsites[$website->getTitle()] = $website->getId();
            if ($website->getId() === $currentWebsite && $website->getDefaultLanguage()) {
                $data['language'] = $website->getDefaultLanguage();
            }
        }

        if (!empty($request->get('page'))) {
            $data = null;
        }

        $form = $this->createForm(PageType::class, $data, [
            'page_websites' => $currentWebsite ? false : $pageWebsites,
            'page_templates' => $config['page_templates'] ?? null,
            'page_languages' => $config['page_languages'] ?? null,
            'page_metatype' => $config['page_metatype'] ?? null,
            'validation_groups' => !$ignore_validation,
            'allow_extra_fields' => true,
        ]);

        $form->handleRequest($request);

        if (!$ignore_validation && $form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if ($currentWebsite) {
                $data['website'] = $currentWebsite;
            }

            $pageUuid = Uuid::uuid1()->toString();
            $success = $this->runCommand($commandBus, PageCreateCommand::class, $data, $pageUuid, 0);

            return $success ? $this->redirectToPage($pageUuid) : $this->errorResponse();
        }

        return $this->render('@cms/Form/form.html.twig', [
            'title' => $translator->trans('Add page'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays the page settings form.
     *
     * @Route("/change-page-settings/{pageUuid}/{version}/", name="cms_change_pagesettings")
     *
     * @param Request                $request
     * @param CommandBus             $commandBus
     * @param AggregateFactory       $aggregateFactory
     * @param TranslatorInterface    $translator
     * @param EntityManagerInterface $entityManager
     * @param string                 $pageUuid
     * @param int                    $version
     *
     * @return JsonResponse|Response
     */
    public function changePageSettings(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, EntityManagerInterface $entityManager, string $pageUuid, int $version)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();
        $config = $this->getParameter('cms');
        $ignore_validation = $request->get('ignore_validation');
        $currentWebsite = $request->get('currentWebsite');

        // Convert Aggregate to data array for form and remove properties we don't want changed.
        $aggregate = $aggregateFactory->build($pageUuid, Page::class, $version, $user->getId());
        $aggregateData = json_decode(json_encode($aggregate), true);
        unset($aggregateData['uuid'], $aggregateData['elements']);
        $aggregateWebsite = $aggregateData['website'] ?? $currentWebsite;

        /** @var Website[] $websites */
        $websites = $user->getWebsites();
        $pageWebsites = [];
        foreach ($websites as $website) {
            $pageWebsites[$website->getTitle()] = $website->getId();
        }
        if ($currentWebsite && $aggregateWebsite !== $currentWebsite && !\in_array($currentWebsite, $pageWebsites, false)) {
            throw new AccessDeniedHttpException('Page does not exist on this website');
        }

        $form = $this->createForm(PageType::class, $aggregateData, [
            'page_websites' => $currentWebsite && count($pageWebsites) === 1 ? false : $pageWebsites,
            'page_templates' => $config['page_templates'] ?? null,
            'page_languages' => $config['page_languages'] ?? null,
            'page_metatype' => $config['page_metatype'] ?? null,
            'validation_groups' => !$ignore_validation,
            'allow_extra_fields' => true,
        ]);

        $form->handleRequest($request);

        // Get differences in data and check if data has changed.
        if (!$ignore_validation && $form->isSubmitted()) {
            $data = $form->getData();

            // Remove data that hasn't changed.
            $data = $this->diff($aggregateData, $data);

            if (empty($data)) {
                $form->addError(new FormError($translator->trans('Data has not changed.')));
            }

            if ($form->isValid()) {
                $success = $this->runCommand($commandBus, PageChangeSettingsCommand::class, $data, $pageUuid, $version, true);

                if ($request->get('ajax')) {
                    return new JsonResponse([
                        'success' => $success,
                        'refresh' => null, // Refreshes whole page.
                    ]);
                }

                return $success ? $this->redirectToPage($pageUuid) : $this->errorResponse();
            }
        }

        return $this->render('@cms/Form/form.html.twig', [
            'title' => 'Change Page Settings',
            'form' => $form->createView(),
        ]);
    }

    /**
     * Creates a new section on a Page Aggregate.
     *
     * @Route("/page/create-section/{pageUuid}/{onVersion}/{section}", name="cms_create_section")
     *
     * @param Request    $request
     * @param CommandBus $commandBus
     * @param string     $pageUuid
     * @param int        $onVersion
     * @param string     $section
     *
     * @return Response
     */
    public function createSection(Request $request, CommandBus $commandBus, string $pageUuid, int $onVersion, string $section)
    {
        $this->denyAccessUnlessGranted('page_edit');

        $success = $this->runCommand($commandBus, PageAddElementCommand::class, [
            'elementName' => 'Section',
            'data' => [
                'section' => $section,
            ],
            'parent' => null,
        ], $pageUuid, $onVersion, true);

        if (!$success) {
            return $this->errorResponse();
        }

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => null, // Refreshes whole page.
            ]);
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Creates a column.
     *
     * @Route("/page/create-column/{pageUuid}/{onVersion}/{parent}/{size}/{breakpoint}", name="cms_page_create_column")
     *
     * @param Request    $request
     * @param CommandBus $commandBus
     * @param string     $pageUuid
     * @param int        $onVersion
     * @param string     $parent
     * @param string     $size
     * @param string     $breakpoint
     *
     * @return JsonResponse|Response
     */
    public function createColumn(Request $request, CommandBus $commandBus, string $pageUuid, int $onVersion, string $parent, string $size, string $breakpoint)
    {
        $this->denyAccessUnlessGranted('page_edit');

        // Check if breakpoint and size are valid.
        if ((int) $size < 1 || (int) $size > 12 || !\in_array($breakpoint, ['xs', 'sm', 'md', 'lg', 'xl'])) {
            return new JsonResponse([
                'success' => false,
                'refresh' => null, // Refreshes whole page.
            ]);
        }

        $success = $this->runCommand($commandBus, PageAddElementCommand::class, [
            'elementName' => 'Column',
            'data' => [
                'width'.strtoupper($breakpoint) => (int) $size,
            ],
            'parent' => $parent,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => $parent,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->errorResponse();
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Resizes a column.
     *
     * @Route("/page/resize-column/{pageUuid}/{onVersion}/{elementUuid}/{size}/{breakpoint}", name="cms_page_resize_column")
     *
     * @param Request    $request
     * @param CommandBus $commandBus
     * @param string     $pageUuid
     * @param int        $onVersion
     * @param string     $elementUuid
     * @param string     $size
     * @param string     $breakpoint
     *
     * @return JsonResponse|Response
     */
    public function resizeColumn(Request $request, CommandBus $commandBus, string $pageUuid, int $onVersion, string $elementUuid, string $size, string $breakpoint)
    {
        $this->denyAccessUnlessGranted('page_edit');

        $success = $this->runCommand($commandBus, PageResizeColumnCommand::class, [
            'uuid' => $elementUuid,
            'size' => (int) $size,
            'breakpoint' => $breakpoint,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementUuid,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->errorResponse();
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Submit qeued events for a specific Page Aggregate and user.
     *
     * @Route("/submit-changes/{pageUuid}/{version}/{qeueUser}", name="cms_submit_changes")
     *
     * @param Request    $request
     * @param CommandBus $commandBus
     * @param string     $pageUuid
     * @param int        $version
     * @param int        $qeueUser
     *
     * @return JsonResponse|Response
     */
    public function submitChanges(Request $request, CommandBus $commandBus, string $pageUuid, int $version, int $qeueUser)
    {
        $this->denyAccessUnlessGranted('page_submit_changes');

        /** @var UserRead $user */
        $user = $this->getUser();

        $form = $this->createFormBuilder()
            ->add('message', TextareaType::class, [
                'label' => 'Commit Message',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Describe the changes you made',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit changes',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $success = $this->runCommand($commandBus, PageSubmitCommand::class, [
                'grantedBy' => $user->getId(),
                'message' => $data['message'],
            ], $pageUuid, $version, true, null, $qeueUser);

            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                ], 200, $this->getToolbarRefreshHeaders());
            }

            return $success ? $this->redirectToPage($pageUuid) : $this->errorResponse();
        }

        return $this->render('@cms/Form/form.html.twig', [
            'title' => 'Submit changes',
            'form' => $form->createView(),
        ]);
    }

    /**
     * Delete all qeued events for a specific Page Aggregate and user.
     *
     * @Route("/discard-changes/{pageUuid}", name="cms_discard_changes")
     *
     * @param EventStore $eventStore
     * @param string     $pageUuid
     *
     * @return Response
     */
    public function discardChanges(EventStore $eventStore, string $pageUuid): Response
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $eventStore->discardQeued($pageUuid, $user->getId());

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Delete the last event from the event qeue
     * for a specific Page Aggregate and user.
     *
     * @Route("/undo-change/{pageUuid}/{version}", name="cms_undo_change")
     *
     * @param EventStore $eventStore
     * @param string     $pageUuid
     * @param int        $version
     *
     * @return Response
     */
    public function undoChange(EventStore $eventStore, string $pageUuid, int $version): Response
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $eventStore->discardLatestQeued($pageUuid, $user->getId(), $version);

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Create an alias for a specific page.
     *
     * @Route("/create-alias/{pageUuid}", name="cms_create_alias")
     *
     * @param Request                $request
     * @param string                 $pageUuid
     * @param EntityManagerInterface $entityManager
     *
     * @return JsonResponse|Response
     */
    public function createAlias(Request $request, string $pageUuid, EntityManagerInterface $entityManager)
    {
        $this->denyAccessUnlessGranted('alias_create');

        $config = $this->getParameter('cms');

        /** @var PageStreamRead|null $pageStreamRead */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);

        if (null === $pageStreamRead) {
            return $this->errorResponse();
        }

        /** @var Website $website */
        $website = $entityManager->getRepository(Website::class)->find($pageStreamRead->getWebsite());
        if (null === $website) {
            return $this->errorResponse();
        }

        if ($website->getDomains()) {
            /** @var \RevisionTen\CMS\Model\Domain $domain */
            $domain = $website->getDomains()->first();
            $websiteUrl = $request->getScheme().'://'.$domain->getDomain();
        } else {
            $websiteUrl = null;
        }
        $slugify = new Slugify();
        $alias_prefix = $config['page_templates'][$pageStreamRead->getTemplate()]['alias_prefix'][$pageStreamRead->getLanguage()] ?? '/';
        $pathSuggestion = $alias_prefix.$slugify->slugify($pageStreamRead->getTitle());

        $form = $this->createFormBuilder(['path' => $pathSuggestion])
            ->add('path', TextType::class, [
                'label' => 'Path',
                'required' => true,
                'attr' => [
                    'placeholder' => $pathSuggestion,
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Create alias',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $path = $data['path'];

            // Create new alias.
            $alias = new Alias();
            $alias->setPath($path);
            $alias->setLanguage($pageStreamRead->getLanguage());
            $alias->setWebsite($website);
            $alias->setPageStreamRead($pageStreamRead);

            // Persist alias.
            $entityManager->persist($alias);
            $entityManager->flush();
            $entityManager->clear();

            return $request->get('ajax') ? new JsonResponse([
                'success' => true,
            ]) : $this->redirectToPage($pageUuid);
        }

        return $this->render('@cms/Form/alias-form.html.twig', [
            'title' => 'Create alias',
            'form' => $form->createView(),
            'websiteUrl' => $websiteUrl,
        ]);
    }

    /**
     * Publishes a Page Aggregate.
     *
     * @Route("/publish-page/{pageUuid}/{version}", name="cms_publish_page")
     *
     * @param Request                $request
     * @param CommandBus             $commandBus
     * @param AggregateFactory       $aggregateFactory
     * @param string                 $pageUuid
     * @param int                    $version
     * @param EntityManagerInterface $entityManager
     *
     * @return JsonResponse|Response
     */
    public function publishPage(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $version, EntityManagerInterface $entityManager)
    {
        $this->denyAccessUnlessGranted('page_publish');

        /** @var PageStreamRead|null $pageStreamRead */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);

        if (null === $pageStreamRead) {
            return $this->errorResponse();
        }

        /**
         * Get latest Page version first.
         *
         * @var Page $page
         */
        $page = $aggregateFactory->build($pageUuid, Page::class);
        $onVersion = $page->getVersion();

        $success = $this->runCommand($commandBus, PagePublishCommand::class, [
            'version' => $version,
        ], $pageUuid, $onVersion);

        if (!$success) {
            return $this->errorResponse();
        }

        // Check if aliases exist for this page.
        $aliases = $pageStreamRead->getAliases();
        if ($this->isGranted('alias_create') && (null === $aliases || empty($aliases) || 0 === \count($aliases))) {
            // The page has no aliases, show modal or page with alias create form.
            $url = $this->generateUrl('cms_create_alias', [
                'pageUuid' => $pageUuid,
            ]);
            return $request->get('ajax') ? new JsonResponse([
                'success' => $success,
                'modal' => $url,
            ], 200, $this->getToolbarRefreshHeaders()) : $this->redirect($url);
        }

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Unpublishes a Page Aggregate.
     *
     * @Route("/unpublish-page/{pageUuid}", name="cms_unpublish_page")
     *
     * @param CommandBus       $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $pageUuid
     *
     * @return JsonResponse|Response
     */
    public function unpublishPage(CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid)
    {
        $this->denyAccessUnlessGranted('page_unpublish');

        /**
         * Get latest Page version first.
         *
         * @var Page $page
         */
        $page = $aggregateFactory->build($pageUuid, Page::class);

        $success = $this->runCommand($commandBus, PageUnpublishCommand::class, [], $pageUuid, $page->getVersion());

        if (!$success) {
            return $this->errorResponse();
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Saves a Page Aggregate Snapshot.
     *
     * @Route("/save-snapshot/{pageUuid}", name="cms_save_snapshot")
     *
     * @param AggregateFactory $aggregateFactory
     * @param SnapshotStore    $snapshotStore
     * @param string           $pageUuid
     *
     * @return Response
     */
    public function saveSnapshot(AggregateFactory $aggregateFactory, SnapshotStore $snapshotStore, string $pageUuid): Response
    {
        /**
         * Get latest Page version first.
         *
         * @var Page $page
         */
        $page = $aggregateFactory->build($pageUuid, Page::class);

        // Save Snapshot.
        $snapshotStore->save($page);

        return $this->redirectToPage($pageUuid);
    }

    /**
     * @Route("/page/add-element/{pageUuid}/{onVersion}/{parent}", name="cms_add_element")
     *
     * @param AggregateFactory $aggregateFactory
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $parent
     * @return Response
     * @throws \Exception
     */
    public function addElement(AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $parent): Response
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        /** @var Page $page */
        $page = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
        if (empty($page->elements)) {
            // Aggregate does not exist, or is empty.
            return $this->errorResponse();
        }

        $config = $this->getParameter('cms');

        // Get the element from the Aggregate.
        $element = PageBaseHandler::getElement($page, $parent);

        // Get an array of accepted children.
        if ($element && isset($element['data'], $element['elementName'], $config['page_elements'][$element['elementName']])) {
            $elementConfig = $config['page_elements'][$element['elementName']];
            $allowedChildren = $elementConfig['children'] ?? null;

            if (empty($allowedChildren)) {
                throw new \Exception('Element type '.$element['elementName'].' does not accept child elements.');
            }

            if (\in_array('all', $allowedChildren, true)) {
                // Filter list of accepted children
                $acceptedChildren = array_filter($config['page_elements'], function ($element) {
                    return isset($element['public']) && $element['public'];
                });
            } else {
                // Filter list of accepted children
                $acceptedChildren = array_filter($config['page_elements'], function ($elementName) use ($allowedChildren) {
                    return \in_array($elementName, $allowedChildren, true);
                }, ARRAY_FILTER_USE_KEY);
            }
        } else {
            // Not a valid element.
            throw new \Exception('Element with uuid '.$parent.' is not a valid parent element.');
        }

        return $this->render('@cms/Form/add-element.html.twig', [
            'title' => 'Add Element',
            'parent' => $parent,
            'children' => $acceptedChildren,
        ]);
    }

    /**
     * @Route("/page/create-element/{elementName}/{pageUuid}/{onVersion}/{parent}", name="cms_create_element")
     *
     * @param Request     $request
     * @param CommandBus  $commandBus
     * @param string      $elementName
     * @param string      $pageUuid
     * @param int         $onVersion
     * @param string|null $parent
     * @param array|null  $data
     * @param string      $form_template
     *
     * @return JsonResponse|Response
     *
     * @throws InterfaceException
     * @throws \Exception
     */
    public function createElementForm(Request $request, CommandBus $commandBus, string $elementName, string $pageUuid, int $onVersion, string $parent = null, array $data = [], string $form_template = null)
    {
        $this->denyAccessUnlessGranted('page_edit');

        $config = $this->getParameter('cms');

        if (isset($config['page_elements'][$elementName])) {
            $elementConfig = $config['page_elements'][$elementName];

            /** @var string $formClass */
            $formClass = $elementConfig['class'];
            $implements = class_implements($formClass);

            if (null === $form_template) {
                $form_template = $elementConfig['form_template'] ?? '@cms/Form/element-form.html.twig';
            }

            if ($implements && \in_array(FormTypeInterface::class, $implements, false)) {

                $ignore_validation = $request->get('ignore_validation');
                $form = $this->createForm(ElementType::class, ['data' => $data], [
                    'elementConfig' => $elementConfig,
                    'validation_groups' => !$ignore_validation,
                ]);
                $form->handleRequest($request);

                if (!$ignore_validation && $form->isSubmitted() && $form->isValid()) {
                    $data = $form->getData()['data'];

                    $success = $this->runCommand($commandBus, PageAddElementCommand::class, [
                        'elementName' => $elementName,
                        'data' => $data,
                        'parent' => $parent,
                    ], $pageUuid, $onVersion, true);

                    if ($request->get('ajax')) {
                        return new JsonResponse([
                            'success' => $success,
                            'refresh' => $parent,
                        ], 200, $this->getToolbarRefreshHeaders());
                    }

                    return $success ? $this->redirectToPage($pageUuid) : $this->errorResponse();
                }

                return $this->render($form_template, [
                    'title' => 'Add Element',
                    'form' => $form->createView(),
                ]);
            } else {
                // Not a valid form type.
                throw new InterfaceException($formClass.' must implement '.FormTypeInterface::class);
            }
        } else {
            throw new \Exception('Element type '.$elementName.' does not exist.');
        }
    }

    /**
     * Displays the edit form for a element.
     *
     * @Route("/page/edit-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_edit_element")
     *
     * @param Request             $request
     * @param CommandBus          $commandBus
     * @param AggregateFactory    $aggregateFactory
     * @param TranslatorInterface $translator
     * @param string              $pageUuid
     * @param int                 $onVersion
     * @param string              $elementUuid
     * @param string              $form_template
     *
     * @return JsonResponse|Response
     *
     * @throws InterfaceException
     * @throws \Exception
     */
    public function editElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, string $pageUuid, int $onVersion, string $elementUuid, string $form_template = null)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        /** @var Page $aggregate */
        $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());

        if (empty($aggregate->elements)) {
            // Aggregate does not exist, or is empty.
            return $this->errorResponse();
        }

        // Get the element from the Aggregate.
        $element = PageBaseHandler::getElement($aggregate, $elementUuid);

        if ($element && isset($element['data'], $element['elementName'])) {
            $data = $element;
            $elementName = $element['elementName'];
            $config = $this->getParameter('cms');

            if (isset($config['page_elements'][$elementName])) {
                $elementConfig = $config['page_elements'][$elementName];
                $formClass = $elementConfig['class'];
                $implements = class_implements($formClass);

                if (null === $form_template) {
                    $form_template = $elementConfig['form_template'] ?? '@cms/Form/element-form.html.twig';
                }

                if ($implements && \in_array(FormTypeInterface::class, $implements, false)) {

                    $ignore_validation = $request->get('ignore_validation');
                    $form = $this->createForm(ElementType::class, $data, [
                        'elementConfig' => $elementConfig,
                        'validation_groups' => !$ignore_validation,
                    ]);
                    $form->handleRequest($request);

                    // Get differences in data and check if data has changed.
                    if (!$ignore_validation && $form->isSubmitted()) {
                        $data = $form->getData()['data'];
                        // Remove data that hasn't changed.
                        $data = $this->diff($element['data'], $data);

                        if (empty($data)) {
                            $form->addError(new FormError($translator->trans('Data has not changed.')));
                        }
                    }

                    if (!$ignore_validation && $form->isSubmitted() && $form->isValid()) {
                        $success = $this->runCommand($commandBus, PageEditElementCommand::class, [
                            'uuid' => $elementUuid,
                            'data' => $data,
                        ], $pageUuid, $onVersion, true);

                        if ($request->get('ajax')) {
                            // Always force reload by setting the uuid to null when editing a section.
                            $elementUuid = 'Section' === $element['elementName'] ? null : $elementUuid;

                            return new JsonResponse([
                                'success' => $success,
                                'refresh' => $elementUuid,
                            ], 200, $this->getToolbarRefreshHeaders());
                        }

                        return $success ? $this->redirectToPage($pageUuid) : $this->errorResponse();
                    }

                    return $this->render($form_template, [
                        'title' => 'Edit Element',
                        'form' => $form->createView(),
                    ]);
                } else {
                    // Not a valid form type.
                    throw new InterfaceException($formClass.' must implement '.FormTypeInterface::class);
                }
            } else {
                throw new \Exception('Element type '.$elementName.' does not exist.');
            }
        } else {
            // Not a valid element.
            throw new \Exception('Element with uuid '.$elementUuid.' is not a valid element.');
        }
    }

    /**
     * Delete a element from a Page Aggregate.
     *
     * @Route("/page/delete-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_delete_element")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $pageUuid
     * @param int              $onVersion
     * @param string           $elementUuid
     *
     * @return JsonResponse|Response
     */
    public function deleteElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        /** @var Page $aggregate */
        $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
        $elementParent = null;
        if (!empty($aggregate->elements)) {
            // Get the parent element from the Aggregate.
            PageBaseHandler::onElement($aggregate, $elementUuid, function ($element, $collection, $parent) use (&$elementParent) {
                $elementParent = $parent['uuid'];
            });
        }

        $success = $this->runCommand($commandBus, PageRemoveElementCommand::class, [
            'uuid' => $elementUuid,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->errorResponse();
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Shift a element up or down on a Page Aggregate.
     *
     * @Route("/page/shift-element/{pageUuid}/{onVersion}/{elementUuid}/{direction}", name="cms_shift_element")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $pageUuid
     * @param int              $onVersion
     * @param string           $elementUuid
     * @param string           $direction
     *
     * @return JsonResponse|Response
     */
    public function shiftElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid, string $direction)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, PageShiftElementCommand::class, [
            'uuid' => $elementUuid,
            'direction' => $direction,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            // Get the element parent so we know what to refresh.
            /** @var Page $aggregate */
            $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
            $elementParent = null;
            if (!empty($aggregate->elements)) {
                // Get the parent element from the Aggregate.
                PageBaseHandler::onElement($aggregate, $elementUuid, function ($element, $collection, $parent) use (&$elementParent) {
                    $elementParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->errorResponse();
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Returns the difference between base array and change array.
     * Works with multidimensional arrays.
     *
     * @param array $base
     * @param array $change
     *
     * @return array
     */
    private function diff(array $base, array $change): array
    {
        $diff = [];

        foreach ($change as $property => $value) {
            $equal = true;

            if (!array_key_exists($property, $base)) {
                // Property is new.
                $equal = false;
            } else {
                $originalValue = $base[$property];

                if (\is_array($value) && \is_array($originalValue)) {
                    // Check if values arrays are identical.
                    if (0 !== strcmp(json_encode($value), json_encode($originalValue))) {
                        // Arrays are not equal.
                        $equal = false;
                    }
                } elseif ($originalValue !== $value) {
                    $equal = false;
                }
            }

            if (!$equal) {
                $diff[$property] = $value;
            }
        }

        return $diff;
    }

    /**
     * Display the frontend page in edit mode.
     *
     * @Route("/edit/{pageUuid}/{user}", name="cms_page_edit")
     *
     * @param Request                $request
     * @param PageService            $pageService
     * @param EntityManagerInterface $entityManager
     * @param AggregateFactory       $aggregateFactory
     * @param EventStore             $eventStore
     * @param TranslatorInterface    $translator
     * @param string                 $pageUuid
     * @param int                    $user             the user that edits the page
     *
     * @return Response
     */
    public function pageEdit(Request $request, PageService $pageService, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, EventStore $eventStore, TranslatorInterface $translator, string $pageUuid, int $user)
    {
        $this->denyAccessUnlessGranted('page_edit');

        $config = $this->getParameter('cms');

        /** @var UserRead $user */
        $user = $entityManager->getRepository(UserRead::class)->find($user);

        /** @var UserRead $realUser */
        $realUser = $this->getUser();

        if ($user->getId() === $realUser->getId()) {
            $edit = true;
        } else {
            $edit = false;
        }

        /** @var Page $page */
        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());

        // Check if user has access to the aggregates current website.
        /** @var ArrayCollection $websites */
        $websites = $user->getWebsites();
        $websites = array_map(function ($website) {
            /** @var Website $website */
            return $website->getId();
        }, $websites->toArray());
        $currentWebsite = $request->get('currentWebsite');
        if ($currentWebsite && $page->website !== $currentWebsite && !\in_array($currentWebsite, $websites, false)) {
            throw new AccessDeniedHttpException('Page does not exist on this website');
        }

        /** @var PageRead $publishedPage */
        $publishedPage = $entityManager->getRepository(PageRead::class)->findOneByUuid($pageUuid);

        // Get all qeued Events for this page.

        /** @var UserRead[] $adminUsers */
        $adminUsers = $entityManager->getRepository(UserRead::class)->findAll();
        $users = [];
        foreach ($adminUsers as $key => $adminUser) {
            $eventStreamObjects = $eventStore->findQeued($pageUuid, null, $page->getStreamVersion() + 1, $adminUser->getId());
            if ($eventStreamObjects) {
                $users[$adminUser->getId()] = [
                    'events' => $eventStreamObjects,
                    'user' => $adminUser,
                ];
            }
        }

        // Get the page template from the template name.
        $template = $config['page_templates'][$page->template]['template'] ?? '@cms/layout.html.twig';

        // Build element info for admin-frontend.js.
        $pageElements = [];
        foreach ($config['page_elements'] as $name => $element) {
            $pageElements[$name] = [
                'icon' => $element['icon'],
                'label' => $translator->trans($name),
                'name' => $name,
                'public' => $element['public'] ?? false,
            ];
        }

        $translations = [
            'addElement' => 'Add Element',
            'delete' => 'Delete',
            'edit' => 'Edit',
            'duplicate' => 'Duplicate',
            'shift' => 'Shift',
            'enable' => 'Enable',
            'disable' => 'Disable',
        ];

        $translations = array_map(function ($value) use ($translator) {
            return $translator->trans($value);
        }, $translations);

        // Convert the page aggregate to a json payload.
        $pageData = json_decode(json_encode($page), true);
        // Hydrate the page with doctrine entities.
        $pageData = $pageService->hydratePage($pageData);

        // Get the pages website.
        $website = isset($pageData['website']) ? $entityManager->getRepository(Website::class)->find($pageData['website']) : null;

        return $this->render($template, [
            'website' => $website,
            'alias' => null,
            'page' => $pageData,
            'publishedVersion' => $publishedPage ? $publishedPage->getVersion() : null,
            'edit' => $edit,
            'user' => $user,
            'users' => $users,
            'config' => $config,
            'pageElements' => $pageElements,
            'translations' => $translations,
        ]);
    }

    /**
     * Display a frontend page.
     *
     * @Route("/page/preview/{pageUuid}", name="cms_page_preview")
     *
     * @param PageService            $pageService
     * @param AggregateFactory       $aggregateFactory
     * @param EntityManagerInterface $entityManager
     * @param string                 $pageUuid
     *
     * @return Response
     */
    public function page(PageService $pageService, AggregateFactory $aggregateFactory, EntityManagerInterface $entityManager, string $pageUuid): Response
    {
        $this->denyAccessUnlessGranted('page_edit');

        $config = $this->getParameter('cms');

        /** @var UserRead $user */
        $user = $this->getUser();

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);
        $alias = (null !== $pageStreamRead->getAliases()) ? $pageStreamRead->getAliases()->first() : null;

        /** @var Page $page */
        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());
        // Convert the page aggregate to a json payload.
        $pageData = json_decode(json_encode($page), true);
        // Filter disabled elements.
        $pageData = $pageService->filterPayload($pageData);
        // Hydrate the page with doctrine entities.
        $pageData = $pageService->hydratePage($pageData);

        // Get the page template from the template name.
        $templateName = $pageData['template'];
        $template = $config['page_templates'][$templateName]['template'] ?? '@cms/layout.html.twig';

        return $this->render($template, [
            'alias' => $alias,
            'page' => $pageData,
            'edit' => false,
            'config' => $config,
        ]);
    }

    /**
     * Redirects to the edit page of a Page Aggregate by its uuid.
     *
     * @param string $pageUuid
     *
     * @return RedirectResponse
     */
    private function redirectToPage(string $pageUuid): RedirectResponse
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var PageStreamRead|null $pageStreamRead */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);

        if (!$pageStreamRead) {
            return $this->redirect('/admin');
        }

        return $this->redirectToRoute('cms_edit_aggregate', [
            'id' => $pageStreamRead->getId(),
        ]);
    }

    /**
     * Clones a page.
     * Must ignore qeued events on page because they might not exist in the future.
     *
     * @Route("/clone-aggregate", name="cms_clone_aggregate")
     *
     * @param Request                $request
     * @param CommandBus             $commandBus
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface    $translator
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function cloneAggregateAction(Request $request, CommandBus $commandBus, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('page_clone');

        /** @var int $id PageStreamRead Id. */
        $id = $request->get('id');

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        $data = [
            'originalUuid' => $pageStreamRead->getUuid(),
            'originalVersion' => $pageStreamRead->getVersion(),
        ];
        $pageUuid = Uuid::uuid1()->toString();

        $success = $this->runCommand($commandBus, PageCloneCommand::class, $data, $pageUuid, 0);

        if (!$success) {
            return $this->errorResponse();
        }

        $this->addFlash(
            'success',
            $translator->trans('Page Cloned')
        );

        return $this->redirectToPage($pageUuid);
    }

    /**
     * @Route("/delete-aggregate", name="cms_delete_aggregate")
     *
     * @param Request                $request
     * @param CommandBus             $commandBus
     * @param EntityManagerInterface $entityManager
     * @param EventStore             $eventStore
     * @param TranslatorInterface    $translator
     *
     * @return Response
     */
    public function deleteAggregateAction(Request $request, CommandBus $commandBus, EntityManagerInterface $entityManager, EventStore $eventStore, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('page_delete');

        /** @var UserRead $user */
        $user = $this->getUser();

        /** @var int $id FormRead Id. */
        $id = $request->get('id');

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        $pageUuid = $pageStreamRead->getUuid();
        $version = $pageStreamRead->getVersion();

        // Discard this users qeued changes before attempting to delete the aggregate.
        $eventStore->discardQeued($pageUuid, $user->getId());

        $success = $this->runCommand($commandBus, PageDeleteCommand::class, [], $pageUuid, $version);

        if (!$success) {
            return $this->errorResponse();
        }

        $this->addFlash(
            'success',
            $translator->trans('Page Deleted')
        );

        return $this->redirect('/admin/?entity=PageStreamRead&action=list');
    }

    /**
     * @Route("/rollback-aggregate/{pageUuid}/{version}", name="cms_rollback_aggregate")
     *
     * @param Request             $request
     * @param CommandBus          $commandBus
     * @param AggregateFactory    $aggregateFactory
     * @param TranslatorInterface $translator
     * @param string              $pageUuid
     * @param int                 $version
     *
     * @return JsonResponse|Response
     */
    public function rollbackAggregateAction(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, string $pageUuid, int $version)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        /** @var Page $pageAggregate */
        $pageAggregate = $aggregateFactory->build($pageUuid, Page::class, $version, $user->getId());

        $versionChoices = [];
        foreach ($pageAggregate->getHistory() as $event) {
            $label = $event['payload']['message'] ?? $translator->trans($event['message']);
            $versionChoices['Version '.$event['version'].' - '.$label] = $event['version'];
        }

        $form = $this->createFormBuilder()
            ->add('previousVersion', ChoiceType::class, [
                'label' => 'Previous Version',
                'required' => true,
                'choices' => $versionChoices,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Rollback',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $success = $this->runCommand($commandBus, PageRollbackCommand::class, [
                'previousVersion' => $data['previousVersion'],
            ], $pageUuid, $version, true);

            if ($success) {
                $this->addFlash(
                    'success',
                    $translator->trans('Page rolled back')
                );
            }

            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                    'refresh' => null, // Refreshes whole page.
                ]);
            }

            return $success ? $this->redirectToPage($pageUuid) : $this->errorResponse();
        }

        return $this->render('@cms/Form/form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Disables an element.
     *
     * @Route("/page/disable-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_page_disableelement")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $pageUuid
     * @param int              $onVersion
     * @param string           $elementUuid
     *
     * @return JsonResponse|Response
     */
    public function disableElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, PageDisableElementCommand::class, [
            'uuid' => $elementUuid,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            /** @var Page $aggregate */
            $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
            $elementParent = null;
            if (!empty($aggregate->elements)) {
                // Get the parent element from the Aggregate.
                PageBaseHandler::onElement($aggregate, $elementUuid, function ($element, $collection, $parent) use (&$elementParent) {
                    $elementParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->errorResponse();
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Enables an element.
     *
     * @Route("/page/enable-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_page_enableelement")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $pageUuid
     * @param int              $onVersion
     * @param string           $elementUuid
     *
     * @return JsonResponse|Response
     */
    public function enableElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, PageEnableElementCommand::class, [
            'uuid' => $elementUuid,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            /** @var Page $aggregate */
            $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
            $elementParent = null;
            if (!empty($aggregate->elements)) {
                // Get the parent element from the Aggregate.
                PageBaseHandler::onElement($aggregate, $elementUuid, function ($element, $collection, $parent) use (&$elementParent) {
                    $elementParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->errorResponse();
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Duplicates an element.
     *
     * @Route("/page/duplicate-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_page_duplicateelement")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $pageUuid
     * @param int              $onVersion
     * @param string           $elementUuid
     *
     * @return JsonResponse|Response
     */
    public function duplicateElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, PageDuplicateElementCommand::class, [
            'uuid' => $elementUuid,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            /** @var Page $aggregate */
            $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
            $elementParent = null;
            if (!empty($aggregate->elements)) {
                // Get the parent element from the Aggregate.
                PageBaseHandler::onElement($aggregate, $elementUuid, function ($element, $collection, $parent) use (&$elementParent) {
                    $elementParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->errorResponse();
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * @Route("/page/save-order/{pageUuid}/{onVersion}", name="cms_page_saveorder")
     *
     * @param Request             $request
     * @param TranslatorInterface $translator
     * @param CommandBus          $commandBus
     * @param string              $pageUuid
     * @param int                 $onVersion
     *
     * @return JsonResponse|RedirectResponse
     */
    public function saveOrder(Request $request, TranslatorInterface $translator, CommandBus $commandBus, string $pageUuid, int $onVersion)
    {
        $this->denyAccessUnlessGranted('page_edit');

        $order = json_decode($request->getContent(), true);

        if ($order && isset($order[0])) {
            $order = $order[0];
            $order = ArrayHelpers::cleanOrderTree($order);
        }

        $success = $this->runCommand($commandBus, PageSaveOrderCommand::class, [
            'order' => $order,
        ], $pageUuid, $onVersion, true);

        if (!$success) {
            return $this->errorResponse();
        } else {
            $this->addFlash(
                'success',
                $translator->trans('Page element order saved')
            );

            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                    'refresh' => null, // refreshes whole page.
                ]);
            }
        }

        return $this->redirectToPage($pageUuid);
    }
}
