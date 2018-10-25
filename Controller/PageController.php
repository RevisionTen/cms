<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\Command\PageAddElementCommand;
use RevisionTen\CMS\Command\PageCloneCommand;
use RevisionTen\CMS\Command\PageDeleteCommand;
use RevisionTen\CMS\Command\PageDisableElementCommand;
use RevisionTen\CMS\Command\PageDuplicateElementCommand;
use RevisionTen\CMS\Command\PageEditElementCommand;
use RevisionTen\CMS\Command\PageEnableElementCommand;
use RevisionTen\CMS\Command\PagePublishCommand;
use RevisionTen\CMS\Command\PageRemoveElementCommand;
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
use RevisionTen\CMS\Model\User;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\Utilities\ArrayHelpers;
use RevisionTen\CQRS\Exception\InterfaceException;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\EventStore;
use RevisionTen\CQRS\Services\SnapshotStore;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Cocur\Slugify\Slugify;

/**
 * Class PageController.
 *
 * @Route("/admin")
 */
class PageController extends Controller
{
    /**
     * A wrapper function to execute a Command.
     * Returns true if the command succeeds.
     *
     * @param CommandBus  $commandBus
     * @param string      $commandClass
     * @param array       $data
     * @param string      $aggregateUuid
     * @param int         $onVersion
     * @param string|null $commandUuid
     * @param int|null    $user
     *
     * @return bool
     */
    public function runCommand(CommandBus $commandBus, string $commandClass, array $data, string $aggregateUuid, int $onVersion, bool $qeue = false, string $commandUuid = null, int $userId = null): bool
    {
        if (null === $userId) {
            /** @var User $user */
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
        return new JsonResponse($this->get('messagebus')->getMessagesJson());
    }

    /**
     * Displays the Page Aggregate create form.
     *
     * @Route("/create-page", name="cms_create_page")
     *
     * @param Request                $request
     * @param CommandBus             $commandBus
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function createPage(Request $request, CommandBus $commandBus, EntityManagerInterface $em)
    {
        $config = $this->getParameter('cms');

        $pageWebsites = [];
        /** @var Website[] $websites */
        $websites = $em->getRepository(Website::class)->findAll();
        foreach ($websites as $website) {
            $pageWebsites[$website->getTitle()] = $website->getId();
        }

        $data = $request->get('page');
        $ignore_validation = $request->get('ignore_validation');

        $form = $this->createForm(PageType::class, $data, [
            'page_websites' => $pageWebsites,
            'page_templates' => $config['page_templates'] ?? null,
            'page_languages' => $config['page_languages'] ?? null,
            'page_metatype' => $config['page_metatype'] ?? null,
        ]);

        if (!$ignore_validation) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();

                $pageUuid = Uuid::uuid1()->toString();
                $success = $this->runCommand($commandBus, PageCreateCommand::class, $data, $pageUuid, 0);

                if ($success) {
                    return $this->redirectToPage($pageUuid);
                } else {
                    return $this->errorResponse();
                }
            }
        }

        return $this->render('@cms/Form/form.html.twig', array(
            'form' => $form->createView(),
        ));
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
     * @param EntityManagerInterface $em
     * @param string                 $pageUuid
     * @param int                    $version
     *
     * @return JsonResponse|Response
     */
    public function changePageSettings(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, EntityManagerInterface $em, string $pageUuid, int $version)
    {
        /** @var User $user */
        $user = $this->getUser();

        $ignore_validation = $request->get('ignore_validation');

        if (!$ignore_validation) {
            // Convert Aggregate to data array for form and remove properties we don't want changed.
            $aggregate = $aggregateFactory->build($pageUuid, Page::class, $version, $user->getId());
            $aggregateData = json_decode(json_encode($aggregate), true);
            unset($aggregateData['uuid']);
            unset($aggregateData['elements']);
        } else {
            $aggregateData = $request->get('page');
        }

        $config = $this->getParameter('cms');

        $pageWebsites = [];
        /** @var Website[] $websites */
        $websites = $em->getRepository(Website::class)->findAll();
        foreach ($websites as $website) {
            $pageWebsites[$website->getTitle()] = $website->getId();
        }

        $form = $this->createForm(PageType::class, $aggregateData, [
            'page_websites' => $pageWebsites,
            'page_templates' => $config['page_templates'] ?? null,
            'page_languages' => $config['page_languages'] ?? null,
            'page_metatype' => $config['page_metatype'] ?? null,
        ]);

        if (!$ignore_validation) {
            $form->handleRequest($request);

            // Get differences in data and check if data has changed.
            if ($form->isSubmitted()) {
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

                    if ($success) {
                        return $this->redirectToPage($pageUuid);
                    } else {
                        return $this->errorResponse();
                    }
                }
            }
        }

        return $this->render('@cms/Form/form.html.twig', array(
            'form' => $form->createView(),
        ));
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
        /** @var User $user */
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
                ]);
            }

            if ($success) {
                return $this->redirectToPage($pageUuid);
            } else {
                return $this->errorResponse();
            }
        }

        return $this->render('@cms/Form/form.html.twig', array(
            'form' => $form->createView(),
        ));
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
        /** @var User $user */
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
        /** @var User $user */
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
        /** @var PageStreamRead|null $pageStreamRead */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);

        if (null === $pageStreamRead) {
            return $this->errorResponse();
        }

        /** @var User $user */
        $user = $this->getUser();

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
        $pathSuggestion = '/'.$slugify->slugify($pageStreamRead->getTitle());

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
            $entityManager->flush($alias);
            $entityManager->clear();

            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => true,
                ]);
            } else {
                return $this->redirectToPage($pageUuid);
            }
        }

        return $this->render('@cms/Form/alias-form.html.twig', array(
            'form' => $form->createView(),
            'contentTitle' => 'Create alias',
            'websiteUrl' => $websiteUrl,
        ));
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
        if (null === $aliases || empty($aliases) || count($aliases) === 0) {
            // The page has no aliases, show modal or page with alias create form.
            $url = $this->generateUrl('cms_create_alias', [
                'pageUuid' => $pageUuid,
            ]);
            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                    'modal' => $url,
                ]);
            } else {
                return $this->redirect($url);
            }
        } else {
            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                ]);
            } else {
                return $this->redirectToPage($pageUuid);
            }
        }
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
        $config = $this->getParameter('cms');

        if (isset($config['page_elements'][$elementName])) {
            $elementConfig = $config['page_elements'][$elementName];

            /** @var string $formClass */
            $formClass = $elementConfig['class'];

            if (null === $form_template) {
                $form_template = isset($elementConfig['form_template']) ? $elementConfig['form_template'] : '@cms/Form/element-form.html.twig';
            }

            // Instantiate the form only to check if it implements FormTypeInterface.
            try {
                /**
                 * Get the form as a service.
                 * # TODO: Is this needed with autowired forms?
                 *
                 * @var FormTypeInterface $formInstance
                 */
                $formInstance = $this->get($formClass);
            } catch (ServiceNotFoundException $e) {
                /**
                 * Construct form type instance.
                 *
                 * @var FormTypeInterface $formInstance
                 */
                $formInstance = new $formClass();
            }

            if ($formInstance instanceof FormTypeInterface) {
                $form = $this->createForm(ElementType::class, ['data' => $data], [
                    'elementConfig' => $elementConfig,
                ]);
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
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
                        ]);
                    }

                    if ($success) {
                        return $this->redirectToPage($pageUuid);
                    } else {
                        return $this->errorResponse();
                    }
                }

                return $this->render($form_template, array(
                    'form' => $form->createView(),
                ));
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
        /** @var User $user */
        $user = $this->getUser();

        /** @var Page $aggregate */
        $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());

        if (empty($aggregate->elements)) {
            // Aggregate does not exist, or is empty.
            return $this->errorResponse();
        }

        // Get the element from the Aggregate.
        $element = PageBaseHandler::getElement($aggregate, $elementUuid);

        if ($element && isset($element['data']) && isset($element['elementName'])) {
            $data = $element;
            $elementName = $element['elementName'];
            $config = $this->getParameter('cms');

            if (isset($config['page_elements'][$elementName])) {
                $elementConfig = $config['page_elements'][$elementName];
                $formClass = $elementConfig['class'];

                if (null === $form_template) {
                    $form_template = isset($elementConfig['form_template']) ? $elementConfig['form_template'] : '@cms/Form/element-form.html.twig';
                }

                // Instantiate the form only to check if it implements FormTypeInterface.
                try {
                    /**
                     * Get the form as a service.
                     *
                     * @var FormTypeInterface $formInstance
                     */
                    $formInstance = $this->get($formClass);
                } catch (ServiceNotFoundException $e) {
                    /**
                     * Construct form type instance.
                     *
                     * @var FormTypeInterface $formInstance
                     */
                    $formInstance = new $formClass();
                }

                if ($formInstance instanceof FormTypeInterface) {
                    $form = $this->createForm(ElementType::class, $data, [
                        'elementConfig' => $elementConfig,
                    ]);
                    $form->handleRequest($request);

                    // Get differences in data and check if data has changed.
                    if ($form->isSubmitted()) {
                        $data = $form->getData()['data'];
                        // Remove data that hasn't changed.
                        $data = $this->diff($element['data'], $data);

                        if (empty($data)) {
                            $form->addError(new FormError($translator->trans('Data has not changed.')));
                        }
                    }

                    if ($form->isSubmitted() && $form->isValid()) {
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
                            ]);
                        }

                        if ($success) {
                            return $this->redirectToPage($pageUuid);
                        } else {
                            return $this->errorResponse();
                        }
                    }

                    return $this->render($form_template, array(
                        'form' => $form->createView(),
                    ));
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
        /** @var User $user */
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
            ]);
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
        /** @var User $user */
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
            ]);
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

                if (is_array($value) && is_array($originalValue)) {
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
     * Display the frontend page in edit mode.
     *
     * @Route("/edit/{pageUuid}/{user}", name="cms_page_edit")
     *
     * @param PageService            $pageService
     * @param EntityManagerInterface $em
     * @param AggregateFactory       $aggregateFactory
     * @param EventStore             $eventStore
     * @param TranslatorInterface    $translator
     * @param string                 $pageUuid
     * @param int                    $user             the user that edits the page
     *
     * @return Response
     */
    public function pageEdit(PageService $pageService, EntityManagerInterface $em, AggregateFactory $aggregateFactory, EventStore $eventStore, TranslatorInterface $translator, string $pageUuid, int $user)
    {
        $config = $this->getParameter('cms');

        /** @var User $user */
        $user = $em->getRepository(User::class)->find($user);

        /** @var User $realUser */
        $realUser = $this->getUser();

        if ($user->getId() === $realUser->getId()) {
            $edit = true;
        } else {
            $edit = false;
        }

        /** @var Page $page */
        $page = $aggregateFactory->build($pageUuid, Page::class, null, $user->getId());

        /** @var PageRead $publishedPage */
        $publishedPage = $em->getRepository(PageRead::class)->findOneByUuid($pageUuid);

        // Get all qeued Events for this page.

        /** @var User[] $adminUsers */
        $adminUsers = $em->getRepository(User::class)->findAll();
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

        return $this->render($template, [
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
     * @param EntityManagerInterface $em
     * @param string                 $pageUuid
     *
     * @return Response
     */
    public function page(PageService $pageService, AggregateFactory $aggregateFactory, EntityManagerInterface $entityManager, string $pageUuid): Response
    {
        $config = $this->getParameter('cms');

        /** @var User $user */
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
     * @return Response
     */
    private function redirectToPage(string $pageUuid): Response
    {
        /** @var EntityManagerInterface $em */
        $em = $this->getDoctrine()->getManager();

        /** @var PageStreamRead|null $pageStreamRead */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);

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
     * @param EntityManagerInterface $em
     * @param TranslatorInterface    $translator
     *
     * @return Response
     */
    public function cloneAggregateAction(Request $request, CommandBus $commandBus, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        /** @var int $id PageStreamRead Id. */
        $id = $request->get('id');

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->find($id);

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
     * @param EntityManagerInterface $em
     * @param EventStore             $eventStore
     * @param TranslatorInterface    $translator
     *
     * @return Response
     */
    public function deleteAggregateAction(Request $request, CommandBus $commandBus, EntityManagerInterface $em, EventStore $eventStore, TranslatorInterface $translator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var int $id FormRead Id. */
        $id = $request->get('id');

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->find($id);

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
        /** @var User $user */
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

            if ($success) {
                return $this->redirectToPage($pageUuid);
            } else {
                return $this->errorResponse();
            }
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
        /** @var User $user */
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
            ]);
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
        /** @var User $user */
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
            ]);
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
        /** @var User $user */
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
            ]);
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
     * @param AggregateFactory    $aggregateFactory
     * @param string              $pageUuid
     * @param int                 $onVersion
     *
     * @return JsonResponse|RedirectResponse
     */
    public function saveOrder(Request $request, TranslatorInterface $translator, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion)
    {
        /** @var User $user */
        $user = $this->getUser();

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
                    'refresh' => null,
                ]);
            }
        }

        return $this->redirectToPage($pageUuid);
    }
}
