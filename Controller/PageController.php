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
use RevisionTen\CMS\Command\PageShiftElementCommand;
use RevisionTen\CMS\Command\PageSubmitCommand;
use RevisionTen\CMS\Command\PageUnpublishCommand;
use RevisionTen\CMS\Form\ElementType;
use RevisionTen\CMS\Form\PageType;
use RevisionTen\CMS\Handler\PageBaseHandler;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Command\PageChangeSettingsCommand;
use RevisionTen\CMS\Command\PageCreateCommand;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\User;
use RevisionTen\CMS\Model\Website;
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
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;

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

        $form = $this->createForm(PageType::class, [], [
            'page_websites' => $pageWebsites,
            'page_templates' => $config['page_templates'] ?? null,
            'page_languages' => $config['page_languages'] ?? null,
            'page_metatype' => $config['page_metatype'] ?? null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $aggregateUuid = Uuid::uuid1()->toString();
            $success = $this->runCommand($commandBus, PageCreateCommand::class, $data, $aggregateUuid, 0);

            if ($success) {
                return $this->redirectToPage($aggregateUuid);
            } else {
                return $this->errorResponse();
            }
        }

        return $this->render('@cms/Form/form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Creates a new section on a Page Aggregate.
     *
     * @Route("/create-section/{pageUuid}/{onVersion}/{section}", name="cms_create_section")
     *
     * @param CommandBus $commandBus
     * @param string     $pageUuid
     * @param int        $onVersion
     * @param string     $section
     *
     * @return Response
     */
    public function createSection(CommandBus $commandBus, string $pageUuid, int $onVersion, string $section)
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
     * Publishes a Page Aggregate.
     *
     * @Route("/publish-page/{pageUuid}/{version}", name="cms_publish_page")
     *
     * @param CommandBus       $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $pageUuid
     * @param int              $version
     *
     * @return JsonResponse|Response
     */
    public function publishPage(CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $version)
    {
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
    public function createElementForm(Request $request, CommandBus $commandBus, string $elementName, string $pageUuid, int $onVersion, string $parent = null, array $data = [], string $form_template = '@cms/Form/form.html.twig')
    {
        $config = $this->getParameter('cms');

        if (isset($config['page_elements'][$elementName])) {
            $elementConfig = $config['page_elements'][$elementName];

            /** @var string $formClass */
            $formClass = $elementConfig['class'];

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
    public function editElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, string $pageUuid, int $onVersion, string $elementUuid, string $form_template = '@cms/Form/form.html.twig')
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

        $aggregate = $aggregateFactory->build($pageUuid, Page::class, $version, $user->getId());

        // Convert Aggregate to data array for form and remove properties we don't want changed.
        $aggregateData = json_decode(json_encode($aggregate), true);
        unset($aggregateData['uuid']);
        unset($aggregateData['elements']);

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
                    ]);
                }

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
     * @param EntityManagerInterface $em
     * @param AggregateFactory       $aggregateFactory
     * @param EventStore             $eventStore
     * @param TranslatorInterface    $translator
     * @param string                 $pageUuid
     * @param int                    $user             the user that edits the page
     *
     * @return Response
     */
    public function pageEdit(EntityManagerInterface $em, AggregateFactory $aggregateFactory, EventStore $eventStore, TranslatorInterface $translator, string $pageUuid, int $user)
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

        return $this->render($template, [
            'alias' => null,
            'page' => $page,
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
        $aggregateUuid = Uuid::uuid1()->toString();

        $success = $this->runCommand($commandBus, PageCloneCommand::class, $data, $aggregateUuid, 0);

        if (!$success) {
            return $this->errorResponse();
        }

        $this->addFlash(
            'success',
            $translator->trans('Page Cloned')
        );

        return $this->redirectToPage($aggregateUuid);
    }

    /**
     * @Route("/delete-aggregate", name="cms_delete_aggregate")
     *
     * @param Request                $request
     * @param CommandBus             $commandBus
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function deleteAggregateAction(Request $request, CommandBus $commandBus, EntityManagerInterface $em): Response
    {
        /** @var int $id FormRead Id. */
        $id = $request->get('id');

        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $em->getRepository(PageStreamRead::class)->find($id);

        if (null === $pageStreamRead) {
            return $this->redirect('/admin');
        }

        $success = $this->runCommand($commandBus, PageDeleteCommand::class, [], $pageStreamRead->getUuid(), $pageStreamRead->getVersion());

        if (!$success) {
            return $this->errorResponse();
        }

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
}
