<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use RevisionTen\CMS\Command\PageAddScheduleCommand;
use RevisionTen\CMS\Command\PageCloneCommand;
use RevisionTen\CMS\Command\PageDeleteCommand;
use RevisionTen\CMS\Command\PageLockCommand;
use RevisionTen\CMS\Command\PagePublishCommand;
use RevisionTen\CMS\Command\PageRemoveScheduleCommand;
use RevisionTen\CMS\Command\PageRollbackCommand;
use RevisionTen\CMS\Command\PageSaveOrderCommand;
use RevisionTen\CMS\Command\PageSubmitCommand;
use RevisionTen\CMS\Command\PageUnlockCommand;
use RevisionTen\CMS\Command\PageUnpublishCommand;
use RevisionTen\CMS\Form\PageType;
use RevisionTen\CMS\Interfaces\AliasSuggesterInterface;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\Domain;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Command\PageChangeSettingsCommand;
use RevisionTen\CMS\Command\PageCreateCommand;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CMS\Services\AliasSuggester;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\Utilities\ArrayHelpers;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\EventStore;
use RevisionTen\CQRS\Services\MessageBus;
use RevisionTen\CQRS\Services\SnapshotStore;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Cocur\Slugify\Slugify;
use function array_map;
use function count;
use function in_array;
use function json_decode;
use function json_encode;

/**
 * Class PageController.
 *
 * @Route("/admin")
 */
class PageController extends AbstractController
{
    private MessageBus $messageBus;

    protected ContainerInterface $fullContainer;

    public function __construct(MessageBus $messageBus, ContainerInterface $fullContainer)
    {
        $this->messageBus = $messageBus;
        $this->fullContainer = $fullContainer;
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
     * @return Response|RedirectResponse
     *
     * @throws Exception
     */
    public function createPage(Request $request, CommandBus $commandBus, TranslatorInterface $translator)
    {
        $this->denyAccessUnlessGranted('page_create');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();
        $config = $this->getParameter('cms');
        $ignore_validation = $request->get('ignore_validation');
        $currentWebsite = (int) $request->get('currentWebsite');

        $data = [];
        $data['language'] = $request->getLocale();
        $pageWebsites = [];
        /**
         * @var Website[] $websites
         */
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

        $pageType = $config['page_type'] ?? PageType::class;
        $templates = $this->getPermittedTemplates('new', $currentWebsite);

        $form = $this->createForm($pageType, $data, [
            'page_websites' => $currentWebsite ? false : $pageWebsites,
            'page_templates' => $templates,
            'page_languages' => $config['page_languages'] ?? null,
            'page_metatype' => $config['page_metatype'] ?? null,
            'validation_groups' => $ignore_validation ? false : null,
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

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.label.addPage', [], 'cms'),
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
     * @throws Exception
     */
    public function changePageSettings(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, EntityManagerInterface $entityManager, string $pageUuid, int $version)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();
        $config = $this->getParameter('cms');
        $ignore_validation = $request->get('ignore_validation');
        $currentWebsite = (int) $request->get('currentWebsite');

        // Convert Aggregate to data array for form and remove properties we don't want changed.
        /**
         * @var Page $aggregate
         */
        $aggregate = $aggregateFactory->build($pageUuid, Page::class, $version, $user->getId());
        $aggregateData = json_decode(json_encode($aggregate), true);
        unset($aggregateData['uuid'], $aggregateData['elements']);
        $aggregateWebsite = $aggregateData['website'] ?? $currentWebsite;

        // Check if the user can edit pages with this template.
        $this->denyAccessUnlessGrantedTemplatePermission('edit', $aggregate->template, $currentWebsite);

        /**
         * @var Website[] $websites
         */
        $websites = $user->getWebsites();
        $pageWebsites = [];
        foreach ($websites as $website) {
            $pageWebsites[$website->getTitle()] = $website->getId();
        }
        if ($currentWebsite && $aggregateWebsite !== $currentWebsite && !in_array($currentWebsite, $pageWebsites, false)) {
            throw new AccessDeniedHttpException('Page does not exist on this website');
        }

        $pageType = $config['page_type'] ?? PageType::class;
        $templates = $this->getPermittedTemplates('edit', $currentWebsite);

        $form = $this->createForm($pageType, $aggregateData, [
            'page_websites' => $currentWebsite && count($pageWebsites) === 1 ? false : $pageWebsites,
            'page_templates' => $templates,
            'page_languages' => $config['page_languages'] ?? null,
            'page_metatype' => $config['page_metatype'] ?? null,
            'validation_groups' => $ignore_validation ? false : null,
            'allow_extra_fields' => true,
        ]);

        $form->handleRequest($request);

        // Get differences in data and check if data has changed.
        if (!$ignore_validation && $form->isSubmitted()) {
            $data = $form->getData();

            // Remove data that hasn't changed.
            $data = ArrayHelpers::diff($aggregateData, $data);

            if (empty($data)) {
                $form->addError(new FormError($translator->trans('admin.validation.dataUnchanged', [], 'cms')));
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

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.label.changePageSettings', [], 'cms'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Submit queued events for a specific Page Aggregate and user.
     *
     * @Route("/submit-changes/{pageUuid}/{version}/{qeueUser}", name="cms_submit_changes")
     *
     * @param Request             $request
     * @param CommandBus          $commandBus
     * @param TranslatorInterface $translator
     * @param string              $pageUuid
     * @param int                 $version
     * @param int                 $qeueUser
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function submitChanges(Request $request, CommandBus $commandBus, TranslatorInterface $translator, string $pageUuid, int $version, int $qeueUser)
    {
        $this->denyAccessUnlessGranted('page_submit_changes');

        // Todo: Check if user can edit pages with this template.

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $formBuilder = $this->createFormBuilder();

        $formBuilder->add('message', TextareaType::class, [
            'label' => 'admin.label.commitMessage',
            'translation_domain' => 'cms',
            'required' => true,
            'attr' => [
                'placeholder' => 'admin.help.commitMessage',
            ],
        ]);

        $formBuilder->add('save', SubmitType::class, [
            'label' => 'admin.btn.submitChanges',
            'translation_domain' => 'cms',
        ]);

        $form = $formBuilder->getForm();;
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

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.label.submitChanges', [], 'cms'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Remove a page schedule.
     *
     * @Route("/remove-schedule/{pageUuid}/{scheduleUuid}/{version}", name="cms_remove_schedule")
     *
     * @param Request    $request
     * @param CommandBus $commandBus
     * @param string     $pageUuid
     * @param string     $scheduleUuid
     * @param int        $version
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function removeSchedule(Request $request, CommandBus $commandBus, string $pageUuid, string $scheduleUuid, int $version)
    {
        $this->denyAccessUnlessGranted('page_schedule');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, PageRemoveScheduleCommand::class, [
            'scheduleUuid' => $scheduleUuid,
        ], $pageUuid, $version, false, null, $user->getId());

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
            ], 200, $this->getToolbarRefreshHeaders());
        }

        return $success ? $this->redirectToPage($pageUuid) : $this->errorResponse();
    }

    /**
     * Schedule a page.
     *
     * @Route("/schedule/{pageUuid}/{version}", name="cms_schedule_page")
     *
     * @param Request                $request
     * @param CommandBus             $commandBus
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface    $translator
     * @param string                 $pageUuid
     * @param int                    $version
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function schedule(Request $request, CommandBus $commandBus, EntityManagerInterface $entityManager, TranslatorInterface $translator, string $pageUuid, int $version)
    {
        $this->denyAccessUnlessGranted('page_schedule');

        /**
         * @var PageStreamRead|null $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);

        if (null === $pageStreamRead) {
            return $this->errorResponse();
        }

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $formBuilder = $this->createFormBuilder();

        $formBuilder->add('startDate', DateTimeType::class, [
            'label' => 'admin.label.startDate',
            'translation_domain' => 'cms',
            'input' => 'timestamp',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
            'html5' => true,
            'required' => false,
        ]);

        $formBuilder->add('endDate', DateTimeType::class, [
            'label' => 'admin.label.endDate',
            'translation_domain' => 'cms',
            'input' => 'timestamp',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
            'html5' => true,
            'required' => false,
        ]);

        $formBuilder->add('save', SubmitType::class, [
            'label' => 'admin.btn.schedule',
            'translation_domain' => 'cms',
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $success = $this->runCommand($commandBus, PageAddScheduleCommand::class, [
                'startDate' => $data['startDate'],
                'endDate' => $data['endDate'],
            ], $pageUuid, $version, false, null, $user->getId());

            $aliases = $pageStreamRead->getAliases();
            $hasAlias = !(null === $aliases || empty($aliases) || 0 === count($aliases));

            return $this->createAliasResponse($pageUuid, $request, $success, $hasAlias);
        }

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.label.schedule', [], 'cms'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Inspect a page.
     *
     * @Route("/inspect/{pageUuid}", name="cms_inspect_page")
     *
     * @param AggregateFactory    $aggregateFactory
     * @param TranslatorInterface $translator
     * @param string              $pageUuid
     *
     * @return JsonResponse|Response
     */
    public function inspect(AggregateFactory $aggregateFactory, TranslatorInterface $translator, string $pageUuid)
    {
        $this->denyAccessUnlessGranted('page_inspect');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());

        return $this->render('@CMS/Backend/Page/inspect.html.twig', [
            'title' => $translator->trans('admin.label.inspectPage', [], 'cms'),
            'page' => $page,
        ]);
    }

    /**
     * Delete all queued events for a specific Page Aggregate and user.
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

        // Todo: Check if user can edit pages with this template.

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $eventStore->discardQueued($pageUuid, $user->getId());

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Delete the last event from the event queue
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

        // Todo: Check if user can edit pages with this template.

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $eventStore->discardLatestQueued($pageUuid, $user->getId(), $version);

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Create an alias for a specific page.
     *
     * @Route("/create-alias/{pageUuid}", name="cms_create_alias")
     *
     * @param Request                $request
     * @param TranslatorInterface    $translator
     * @param EntityManagerInterface $entityManager
     * @param string                 $pageUuid
     *
     * @return JsonResponse|Response
     */
    public function createAlias(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager, string $pageUuid)
    {
        $this->denyAccessUnlessGranted('alias_create');

        $config = $this->getParameter('cms');

        /**
         * @var PageStreamRead|null $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);

        if (null === $pageStreamRead) {
            return $this->errorResponse();
        }

        /**
         * @var Website $website
         */
        $website = $entityManager->getRepository(Website::class)->find($pageStreamRead->getWebsite());
        if (null === $website) {
            return $this->errorResponse();
        }

        if ($website->getDomains()) {
            /**
             * @var Domain $domain
             */
            $domain = $website->getDomains()->first();
            $websiteUrl = $request->getScheme().'://'.$domain->getDomain();
        } else {
            $websiteUrl = null;
        }

        $suggesterClass = $config['page_templates'][$pageStreamRead->getTemplate()]['alias_suggester'] ?? null;
        if ($suggesterClass) {
            /**
             * @var AliasSuggesterInterface $suggester
             */
            $suggester = $this->fullContainer->get($suggesterClass);
        } else {
            $suggester = new AliasSuggester($config);
        }

        $pathSuggestion = $suggester->suggest($pageStreamRead);

        $formBuilder = $this->createFormBuilder(['path' => $pathSuggestion]);

        $formBuilder->add('path', TextType::class, [
            'label' => 'admin.label.path',
            'translation_domain' => 'cms',
            'required' => true,
            'attr' => [
                'placeholder' => $pathSuggestion,
            ],
        ]);

        $formBuilder->add('save', SubmitType::class, [
            'label' => 'admin.btn.createAlias',
            'translation_domain' => 'cms',
        ]);

        $form = $formBuilder->getForm();
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

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.label.createAlias', [], 'cms'),
            'form' => $form->createView(),
            'websiteUrl' => $websiteUrl,
        ]);
    }

    /**
     * Redirect to alias create form If the page has no aliases.
     *
     * @param string  $pageUuid
     * @param Request $request
     * @param bool    $success
     * @param bool    $hasAlias
     *
     * @return JsonResponse|RedirectResponse
     */
    public function createAliasResponse(string $pageUuid, Request $request, bool $success, bool $hasAlias)
    {
        // Check if aliases exist for this page.
        if (!$hasAlias && $this->isGranted('alias_create')) {
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

        return $success ? $this->redirectToPage($pageUuid) : $this->errorResponse();
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
     * @throws Exception
     */
    public function publishPage(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $version, EntityManagerInterface $entityManager)
    {
        $this->denyAccessUnlessGranted('page_publish');

        /**
         * @var PageStreamRead|null $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);

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
            'version' => $version, // Todo: Not needed anymore, see listener.
        ], $pageUuid, $onVersion);

        if (!$success) {
            return $this->errorResponse();
        }

        $aliases = $pageStreamRead->getAliases();
        $hasAlias = !(null === $aliases || empty($aliases) || 0 === count($aliases));

        return $this->createAliasResponse($pageUuid, $request, $success, $hasAlias);
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
     * @throws Exception
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
     * @param int                    $user
     *
     * @return Response
     */
    public function pageEdit(Request $request, PageService $pageService, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, EventStore $eventStore, TranslatorInterface $translator, string $pageUuid, int $user): Response
    {
        $this->denyAccessUnlessGranted('page_edit');

        $config = $this->getParameter('cms');

        /**
         * @var UserRead|null $user
         */
        $user = $entityManager->getRepository(UserRead::class)->find($user);

        /**
         * @var UserRead $realUser
         */
        $realUser = $this->getUser();

        if ($user->getId() === $realUser->getId()) {
            $edit = true;
        } else {
            $edit = false;
        }

        /**
         * @var Page $page
         */
        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());

        // Check if user has access to the aggregates current website.
        /**
         * @var ArrayCollection $websites
         */
        $websites = $user->getWebsites();
        $websites = array_map(static function ($website) {
            /**
             * @var Website $website
             */
            return $website->getId();
        }, $websites->toArray());
        $currentWebsite = (int) $request->get('currentWebsite');
        if ($currentWebsite && $page->website !== $currentWebsite && !in_array($currentWebsite, $websites, false)) {
            throw new AccessDeniedHttpException('Page does not exist on this website');
        }

        // Check if the user can edit pages with this template.
        $this->denyAccessUnlessGrantedTemplatePermission('edit', $page->template, $currentWebsite);

        // Get the first alias for this page.
        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);
        $alias = (null !== $pageStreamRead->getAliases()) ? $pageStreamRead->getAliases()->first() : null;

        // Get all queued Events for this page.
        /**
         * @var UserRead[] $adminUsers
         */
        $adminUsers = $entityManager->getRepository(UserRead::class)->findAll();
        $users = [];
        foreach ($adminUsers as $key => $adminUser) {
            $eventStreamObjects = $eventStore->findQueued($pageUuid, $adminUser->getId(), null, $page->getStreamVersion() + 1);
            if ($eventStreamObjects) {
                $users[$adminUser->getId()] = [
                    'events' => $eventStreamObjects,
                    'user' => $adminUser,
                ];
            }
        }

        // Get the page template from the template name.
        $template = $config['page_templates'][$page->template]['template'] ?? '@CMS/Frontend/Page/simple.html.twig';

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
            'addElement' => $translator->trans('admin.btn.addElement', [], 'cms'),
            'delete' => $translator->trans('admin.btn.delete', [], 'cms'),
            'edit' => $translator->trans('admin.btn.edit', [], 'cms'),
            'duplicate' => $translator->trans('admin.btn.duplicate', [], 'cms'),
            'shift' => $translator->trans('admin.btn.shift', [], 'cms'),
            'enable' => $translator->trans('admin.btn.enable', [], 'cms'),
            'disable' => $translator->trans('admin.btn.disable', [], 'cms'),
            'savePadding' => $translator->trans('admin.btn.savePadding', [], 'cms'),
        ];

        // Convert the page aggregate to a json payload.
        $pageData = json_decode(json_encode($page), true);
        // Hydrate the page with doctrine entities.
        $pageData = $pageService->hydratePage($pageData);

        // Get the pages website.
        $website = isset($pageData['website']) ? $entityManager->getRepository(Website::class)->find($pageData['website']) : null;

        return $this->render($template, [
            'website' => $website,
            'alias' => $alias,
            'page' => $pageData,
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

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);
        $alias = (null !== $pageStreamRead->getAliases()) ? $pageStreamRead->getAliases()->first() : null;


        $websiteId = $pageStreamRead->getWebsite();
        /**
         * @var Website $website
         */
        $website = $entityManager->getRepository(Website::class)->find($websiteId);

        /**
         * @var Page $page
         */
        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());
        // Convert the page aggregate to a json payload.
        $pageData = json_decode(json_encode($page), true);
        // Filter disabled elements.
        $pageData = $pageService->filterPayload($pageData);
        // Hydrate the page with doctrine entities.
        $pageData = $pageService->hydratePage($pageData);

        // Get the page template from the template name.
        $templateName = $pageData['template'];
        $template = $config['page_templates'][$templateName]['template'] ?? '@CMS/Frontend/Page/simple.html.twig';

        return $this->render($template, [
            'alias' => $alias,
            'website' => $website,
            'page' => $pageData,
            'edit' => false,
            'config' => $config,
        ]);
    }

    /**
     * Clones a page.
     *
     * Must ignore queued events on page because they might not exist in the future.
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
     * @throws Exception
     */
    public function cloneAggregateAction(Request $request, CommandBus $commandBus, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('page_clone');

        /**
         * @var int $id PageStreamRead Id.
         */
        $id = $request->get('id');

        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        // Check if the user can create pages with this template.
        $currentWebsite = (int) $request->get('currentWebsite');
        $this->denyAccessUnlessGrantedTemplatePermission('new', $pageStreamRead->getTemplate(), $currentWebsite);

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
            $translator->trans('admin.label.pageCloneSuccess', [], 'cms')
        );

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Locks a page.
     *
     * Must ignore queued events on page because they might not exist in the future.
     *
     * @Route("/page/lock", name="cms_lock_page")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @param EventStore $eventStore
     *
     * @return Response
     * @throws Exception
     */
    public function lockPage(Request $request, CommandBus $commandBus, EntityManagerInterface $entityManager, TranslatorInterface $translator, EventStore $eventStore): Response
    {
        $this->denyAccessUnlessGranted('page_lock_unlock');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        /**
         * @var int $id PageStreamRead Id.
         */
        $id = $request->get('id');

        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        $pageUuid = $pageStreamRead->getUuid();
        $version = $pageStreamRead->getVersion();

        // Discard this users queued changes before attempting to lock the page.
        $eventStore->discardQueued($pageUuid, $user->getId());

        $success = $this->runCommand($commandBus, PageLockCommand::class, [], $pageUuid, $version);

        if (!$success) {
            return $this->errorResponse();
        }

        $this->addFlash(
            'success',
            $translator->trans('admin.label.pageLockSuccess', [], 'cms')
        );

        return $this->redirectToPage($pageUuid);
    }

    /**
     * Unlocks a page.
     *
     * Must ignore queued events on page because they might not exist in the future.
     *
     * @Route("/page/unlock", name="cms_unlock_page")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @param EventStore $eventStore
     *
     * @return Response
     * @throws Exception
     */
    public function unlockPage(Request $request, CommandBus $commandBus, EntityManagerInterface $entityManager, TranslatorInterface $translator, EventStore $eventStore): Response
    {
        $this->denyAccessUnlessGranted('page_lock_unlock');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        /**
         * @var int $id PageStreamRead Id.
         */
        $id = $request->get('id');

        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        $pageUuid = $pageStreamRead->getUuid();
        $version = $pageStreamRead->getVersion();

        // Discard this users queued changes before attempting to lock the page.
        $eventStore->discardQueued($pageUuid, $user->getId());

        $success = $this->runCommand($commandBus, PageUnlockCommand::class, [], $pageUuid, $version);

        if (!$success) {
            return $this->errorResponse();
        }

        $this->addFlash(
            'success',
            $translator->trans('admin.label.pageUnlockSuccess', [], 'cms')
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
     * @throws Exception
     */
    public function deleteAggregateAction(Request $request, CommandBus $commandBus, EntityManagerInterface $entityManager, EventStore $eventStore, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('page_delete');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        /**
         * @var int $id FormRead Id.
         */
        $id = $request->get('id');

        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        // Check if the user can delete pages with this template.
        $currentWebsite = (int) $request->get('currentWebsite');
        $this->denyAccessUnlessGrantedTemplatePermission('delete', $pageStreamRead->getTemplate(), $currentWebsite);

        $pageUuid = $pageStreamRead->getUuid();
        $version = $pageStreamRead->getVersion();

        // Discard this users queued changes before attempting to delete the aggregate.
        $eventStore->discardQueued($pageUuid, $user->getId());

        $success = $this->runCommand($commandBus, PageDeleteCommand::class, [], $pageUuid, $version);

        if (!$success) {
            return $this->errorResponse();
        }

        $this->addFlash(
            'success',
            $translator->trans('admin.label.pageDeleteSuccess', [], 'cms')
        );

        return $this->redirectToRoute('cms_list_pages');
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
     * @throws Exception
     */
    public function rollbackAggregateAction(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, string $pageUuid, int $version)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        /**
         * @var Page $pageAggregate
         */
        $pageAggregate = $aggregateFactory->build($pageUuid, Page::class, $version, $user->getId());

        // Check if the user can edit pages with this template.
        $currentWebsite = (int) $request->get('currentWebsite');
        $this->denyAccessUnlessGrantedTemplatePermission('edit', $pageAggregate->template, $currentWebsite);

        $versionChoices = [];
        foreach ($pageAggregate->getHistory() as $event) {
            $label = $event['payload']['message'] ?? $translator->trans($event['message']);
            $versionChoices['Version '.$event['version'].' - '.$label] = $event['version'];
        }

        $formBuilder = $this->createFormBuilder();

        $formBuilder->add('previousVersion', ChoiceType::class, [
            'label' => 'admin.label.previousVersion',
            'translation_domain' => 'cms',
            'required' => true,
            'choices' => $versionChoices,
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $formBuilder->add('save', SubmitType::class, [
            'label' => 'admin.btn.rollback',
            'translation_domain' => 'cms',
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $success = $this->runCommand($commandBus, PageRollbackCommand::class, [
                'previousVersion' => $data['previousVersion'],
            ], $pageUuid, $version, true);

            if ($success) {
                $this->addFlash(
                    'success',
                    $translator->trans('admin.label.pageRollbackSuccess', [], 'cms')
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

        return $this->render('@CMS/Backend/Form/form.html.twig', array(
            'form' => $form->createView(),
            'title' => $translator->trans('admin.btn.rollback', [], 'cms'),
        ));
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
     * @throws Exception
     */
    public function saveOrder(Request $request, TranslatorInterface $translator, CommandBus $commandBus, string $pageUuid, int $onVersion)
    {
        $this->denyAccessUnlessGranted('page_edit');

        // Todo: Check if user can save order for this page template.

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
        }

        $this->addFlash(
            'success',
            $translator->trans('admin.label.pageSaveOrderSuccess', [], 'cms')
        );

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => null, // refreshes whole page.
            ]);
        }

        return $this->redirectToPage($pageUuid);
    }

    /**
     * @return array
     */
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
     * @param bool        $queue
     * @param string|null $commandUuid
     * @param int|null    $userId
     *
     * @return bool
     * @throws Exception
     */
    private function runCommand(CommandBus $commandBus, string $commandClass, array $data, string $aggregateUuid, int $onVersion, bool $queue = false, string $commandUuid = null, int $userId = null): bool
    {
        if (null === $userId) {
            /**
             * @var UserRead $user
             */
            $user = $this->getUser();
            $userId = $user->getId();
        }

        $command = new $commandClass($userId, $commandUuid, $aggregateUuid, $onVersion, $data);

        return $commandBus->dispatch($command, $queue);
    }

    /**
     * Returns info from the messageBus.
     *
     * @return JsonResponse
     */
    private function errorResponse(): JsonResponse
    {
        return new JsonResponse($this->messageBus->getMessagesJson());
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
        /**
         * @var EntityManagerInterface $entityManager
         */
        $entityManager = $this->getDoctrine()->getManager();

        /**
         * @var PageStreamRead|null $pageStreamRead
         */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);

        if (!$pageStreamRead) {
            return $this->redirect('/admin');
        }

        return $this->redirectToRoute('cms_edit_aggregate', [
            'id' => $pageStreamRead->getId(),
        ]);
    }

    /**
     * @param string $permissionName
     * @param int    $currentWebsite
     *
     * @return array
     */
    private function getPermittedTemplates(string $permissionName, int $currentWebsite): array
    {
        $config = $this->getParameter('cms');
        $pageTemplates = $config['page_templates'] ?? null;
        $templates = [];
        foreach ($pageTemplates as $template => $templateConfig) {
            if ('new' === $permissionName || 'create' === $permissionName) {
                // Create permission can be "new" or "create"
                $permission = $templateConfig['permissions']['create'] ?? ($templateConfig['permissions']['new'] ?? null);
            } else {
                $permission = $templateConfig['permissions'][$permissionName] ?? null;
            }
            // Check if the website matches.
            if (!empty($templateConfig['websites']) && !in_array($currentWebsite, $templateConfig['websites'], true)) {
                // Current website is not in defined websites.
                continue;
            }
            // Check if permission is not explicitly set or user is granted the permission.
            if (null === $permission || $this->isGranted($permission)) {
                $templates[$template] = $templateConfig;
            }
        }

        return $templates;
    }

    /**
     * Checks if the user has access to a provided page template.
     *
     * @param string $permissionName
     * @param string $template
     * @param int    $currentWebsite
     *
     * @return void
     */
    private function denyAccessUnlessGrantedTemplatePermission(string $permissionName, string $template, int $currentWebsite): void
    {
        $config = $this->getParameter('cms');
        $permission = $config['page_templates'][$template]['permissions'][$permissionName] ?? null;
        $websites = $config['page_templates'][$template]['websites'] ?? null;

        if (null === $permission) {
            // Permission is not explicitly set, grant access.
            return;
        }

        // Check if the website matches.
        if (null !== $websites && !in_array($currentWebsite, $websites, true)) {
            // Current website is not is defined websites.
            throw new AccessDeniedHttpException();
        }

        // Check if permission is granted.
        if (!$this->isGranted($permission)) {
            throw new AccessDeniedHttpException();
        }
    }
}
