<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Exception;
use InvalidArgumentException;
use RevisionTen\CMS\Command\PageAddElementCommand;
use RevisionTen\CMS\Command\PageDisableElementCommand;
use RevisionTen\CMS\Command\PageDuplicateElementCommand;
use RevisionTen\CMS\Command\PageEditElementCommand;
use RevisionTen\CMS\Command\PageEnableElementCommand;
use RevisionTen\CMS\Command\PageRemoveElementCommand;
use RevisionTen\CMS\Command\PageResizeColumnCommand;
use RevisionTen\CMS\Command\PageShiftElementCommand;
use RevisionTen\CMS\Form\ElementType;
use RevisionTen\CMS\Handler\PageBaseHandler;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Utilities\ArrayHelpers;
use RevisionTen\CQRS\Exception\InterfaceException;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use function array_filter;
use function class_implements;
use function in_array;
use function strtoupper;

/**
 * Class ElementController.
 *
 * @Route("/admin")
 */
class ElementController extends AbstractController
{
    /** @var MessageBus */
    private $messageBus;

    public function __construct(MessageBus $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    private function _getToolbarRefreshHeaders(): array
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
     * @param CommandBus $commandBus
     * @param string $commandClass
     * @param array $data
     * @param string $aggregateUuid
     * @param int $onVersion
     * @param bool $queue
     * @param string|NULL $commandUuid
     * @param int|NULL $userId
     *
     * @return bool
     * @throws Exception
     */
    private function _runCommand(CommandBus $commandBus, string $commandClass, array $data, string $aggregateUuid, int $onVersion, bool $queue = false, string $commandUuid = null, int $userId = null): bool
    {
        if (null === $userId) {
            /** @var UserRead $user */
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
    private function _errorResponse(): JsonResponse
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
    private function _redirectToPage(string $pageUuid): RedirectResponse
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var PageStreamRead|null $pageStreamRead */
        $pageStreamRead = $entityManager->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);

        if (!$pageStreamRead) {
            return $this->redirect('/admin');
        }

        return $this->redirectToRoute('cms_edit_aggregate', [
            'id' => $pageStreamRead->getId(),
        ]);
    }

    /**
     * @Route("/page/add-element/{pageUuid}/{onVersion}/{parent}", name="cms_add_element")
     *
     * @param AggregateFactory $aggregateFactory
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $parent
     * @return Response
     * @throws Exception
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
            return $this->_errorResponse();
        }

        $config = $this->getParameter('cms');

        // Get the element from the Aggregate.
        $element = PageBaseHandler::getElement($page, $parent);

        // Get an array of accepted children.
        if ($element && isset($element['data'], $element['elementName'], $config['page_elements'][$element['elementName']])) {
            $elementConfig = $config['page_elements'][$element['elementName']];
            $allowedChildren = $elementConfig['children'] ?? null;

            if (empty($allowedChildren)) {
                throw new InvalidArgumentException('Element type '.$element['elementName'].' does not accept child elements.');
            }

            if (in_array('all', $allowedChildren, true)) {
                // Filter list of accepted children
                $acceptedChildren = array_filter($config['page_elements'], static function ($element) {
                    return isset($element['public']) && $element['public'];
                });
            } else {
                // Filter list of accepted children
                $acceptedChildren = array_filter($config['page_elements'], static function ($elementName) use ($allowedChildren) {
                    return in_array($elementName, $allowedChildren, true);
                }, ARRAY_FILTER_USE_KEY);
            }
        } else {
            // Not a valid element.
            throw new InvalidArgumentException('Element with uuid '.$parent.' is not a valid parent element.');
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
     * @throws Exception
     */
    public function createElementForm(Request $request, CommandBus $commandBus, string $elementName, string $pageUuid, int $onVersion, string $parent = null, array $data = [], string $form_template = null)
    {
        $this->denyAccessUnlessGranted('page_edit');

        $config = $this->getParameter('cms');

        // Get element config.
        $elementConfig = $config['page_elements'][$elementName] ?? null;
        if (null === $elementConfig) {
            throw new InvalidArgumentException('Element type '.$elementName.' does not exist.');
        }

        // Get element form template.
        if (null === $form_template) {
            $form_template = $elementConfig['form_template'] ?? '@cms/Form/element-form.html.twig';
        }

        // Check if the element is valid form type.
        /** @var string $formClass */
        $formClass = $elementConfig['class'];
        $implements = class_implements($formClass);
        $validFormType = $implements && in_array(FormTypeInterface::class, $implements, false);
        if (!$validFormType) {
            // Not a valid form type.
            throw new InterfaceException($formClass.' must implement '.FormTypeInterface::class);
        }

        $ignore_validation = $request->get('ignore_validation');
        $form = $this->createForm(ElementType::class, ['data' => $data], [
            'elementConfig' => $elementConfig,
            'validation_groups' => $ignore_validation ? false : null,
        ]);
        $form->handleRequest($request);

        if (!$ignore_validation && $form->isSubmitted() && $form->isValid()) {
            $data = $form->getData()['data'];

            $success = $this->_runCommand($commandBus, PageAddElementCommand::class, [
                'elementName' => $elementName,
                'data' => $data,
                'parent' => $parent,
            ], $pageUuid, $onVersion, true);

            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                    'refresh' => $parent,
                ], 200, $this->_getToolbarRefreshHeaders());
            }

            return $success ? $this->_redirectToPage($pageUuid) : $this->_errorResponse();
        }

        return $this->render($form_template, [
            'title' => 'Add Element',
            'form' => $form->createView(),
        ]);
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
     * @throws Exception
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
            return $this->_errorResponse();
        }

        // Get the element from the Aggregate.
        $element = PageBaseHandler::getElement($aggregate, $elementUuid);

        // Check if element is valid.
        $validElement = $element && isset($element['data'], $element['elementName']);
        if (!$validElement) {
            throw new InvalidArgumentException('Element with uuid '.$elementUuid.' is not a valid element.');
        }

        $data = $element;
        $elementName = $element['elementName'];
        $config = $this->getParameter('cms');

        // Get element condig.
        $elementConfig = $config['page_elements'][$elementName] ?? null;
        if (null === $elementConfig) {
            throw new InvalidArgumentException('Element type '.$elementName.' does not exist.');
        }

        // Get element form template.
        if (null === $form_template) {
            $form_template = $elementConfig['form_template'] ?? '@cms/Form/element-form.html.twig';
        }

        // Check if its a valid form type.
        $formClass = $elementConfig['class'];
        $implements = class_implements($formClass);
        $validFormType = $implements && in_array(FormTypeInterface::class, $implements, false);
        if (!$validFormType) {
            // Not a valid form type.
            throw new InterfaceException($formClass.' must implement '.FormTypeInterface::class);
        }

        $ignore_validation = $request->get('ignore_validation');
        $form = $this->createForm(ElementType::class, $data, [
            'elementConfig' => $elementConfig,
            'validation_groups' => $ignore_validation ? false : null,
        ]);
        $form->handleRequest($request);

        // Get differences in data and check if data has changed.
        if (!$ignore_validation && $form->isSubmitted()) {
            $data = $form->getData()['data'];
            // Remove data that hasn't changed.
            $data = ArrayHelpers::diff($element['data'], $data);

            if (empty($data)) {
                $form->addError(new FormError($translator->trans('Data has not changed.')));
            }
        }

        if (!$ignore_validation && $form->isSubmitted() && $form->isValid()) {
            $success = $this->_runCommand($commandBus, PageEditElementCommand::class, [
                'uuid' => $elementUuid,
                'data' => $data,
            ], $pageUuid, $onVersion, true);

            if ($request->get('ajax')) {
                // Always force reload by setting the uuid to null when editing a section.
                $elementUuid = 'Section' === $element['elementName'] ? null : $elementUuid;

                return new JsonResponse([
                    'success' => $success,
                    'refresh' => $elementUuid,
                ], 200, $this->_getToolbarRefreshHeaders());
            }

            return $success ? $this->_redirectToPage($pageUuid) : $this->_errorResponse();
        }

        return $this->render($form_template, [
            'title' => 'Edit Element',
            'form' => $form->createView(),
        ]);
    }

    /**
     * Delete a element from a Page Aggregate.
     *
     * @Route("/page/delete-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_delete_element")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $elementUuid
     *
     * @return JsonResponse|Response
     * @throws Exception
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
            PageBaseHandler::onElement($aggregate, $elementUuid, static function ($element, $collection, $parent) use (&$elementParent) {
                $elementParent = $parent['uuid'];
            });
        }

        $success = $this->_runCommand($commandBus, PageRemoveElementCommand::class, [
            'uuid' => $elementUuid,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->_getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->_errorResponse();
        }

        return $this->_redirectToPage($pageUuid);
    }

    /**
     * Shift a element up or down on a Page Aggregate.
     *
     * @Route("/page/shift-element/{pageUuid}/{onVersion}/{elementUuid}/{direction}", name="cms_shift_element")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $elementUuid
     * @param string $direction
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function shiftElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid, string $direction)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->_runCommand($commandBus, PageShiftElementCommand::class, [
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
                PageBaseHandler::onElement($aggregate, $elementUuid, static function ($element, $collection, $parent) use (&$elementParent) {
                    $elementParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->_getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->_errorResponse();
        }

        return $this->_redirectToPage($pageUuid);
    }

    /**
     * Duplicates an element.
     *
     * @Route("/page/duplicate-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_page_duplicateelement")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $elementUuid
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function duplicateElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->_runCommand($commandBus, PageDuplicateElementCommand::class, [
            'uuid' => $elementUuid,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            /** @var Page $aggregate */
            $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
            $elementParent = null;
            if (!empty($aggregate->elements)) {
                // Get the parent element from the Aggregate.
                PageBaseHandler::onElement($aggregate, $elementUuid, static function ($element, $collection, $parent) use (&$elementParent) {
                    $elementParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->_getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->_errorResponse();
        }

        return $this->_redirectToPage($pageUuid);
    }

    /**
     * Enables an element.
     *
     * @Route("/page/enable-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_page_enableelement")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $elementUuid
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function enableElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->_runCommand($commandBus, PageEnableElementCommand::class, [
            'uuid' => $elementUuid,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            /** @var Page $aggregate */
            $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
            $elementParent = null;
            if (!empty($aggregate->elements)) {
                // Get the parent element from the Aggregate.
                PageBaseHandler::onElement($aggregate, $elementUuid, static function ($element, $collection, $parent) use (&$elementParent) {
                    $elementParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->_getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->_errorResponse();
        }

        return $this->_redirectToPage($pageUuid);
    }

    /**
     * Disables an element.
     *
     * @Route("/page/disable-element/{pageUuid}/{onVersion}/{elementUuid}", name="cms_page_disableelement")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param AggregateFactory $aggregateFactory
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $elementUuid
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function disableElement(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $pageUuid, int $onVersion, string $elementUuid)
    {
        $this->denyAccessUnlessGranted('page_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->_runCommand($commandBus, PageDisableElementCommand::class, [
            'uuid' => $elementUuid,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            /** @var Page $aggregate */
            $aggregate = $aggregateFactory->build($pageUuid, Page::class, $onVersion, $user->getId());
            $elementParent = null;
            if (!empty($aggregate->elements)) {
                // Get the parent element from the Aggregate.
                PageBaseHandler::onElement($aggregate, $elementUuid, static function ($element, $collection, $parent) use (&$elementParent) {
                    $elementParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementParent,
            ], 200, $this->_getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->_errorResponse();
        }

        return $this->_redirectToPage($pageUuid);
    }

    /**
     * Creates a column.
     *
     * @Route("/page/create-column/{pageUuid}/{onVersion}/{parent}/{size}/{breakpoint}", name="cms_page_create_column")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $parent
     * @param string $size
     * @param string $breakpoint
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function createColumn(Request $request, CommandBus $commandBus, string $pageUuid, int $onVersion, string $parent, string $size, string $breakpoint)
    {
        $this->denyAccessUnlessGranted('page_edit');

        // Check if breakpoint and size are valid.
        if ((int) $size < 1 || (int) $size > 12 || !in_array($breakpoint, ['xs', 'sm', 'md', 'lg', 'xl'])) {
            return new JsonResponse([
                'success' => false,
                'refresh' => null, // Refreshes whole page.
            ]);
        }

        $success = $this->_runCommand($commandBus, PageAddElementCommand::class, [
            'elementName' => 'Column',
            'data' => [
                'width'. strtoupper($breakpoint) => (int) $size,
            ],
            'parent' => $parent,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => $parent,
            ], 200, $this->_getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->_errorResponse();
        }

        return $this->_redirectToPage($pageUuid);
    }

    /**
     * Resizes a column.
     *
     * @Route("/page/resize-column/{pageUuid}/{onVersion}/{elementUuid}/{size}/{breakpoint}", name="cms_page_resize_column")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $elementUuid
     * @param string $size
     * @param string $breakpoint
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function resizeColumn(Request $request, CommandBus $commandBus, string $pageUuid, int $onVersion, string $elementUuid, string $size, string $breakpoint)
    {
        $this->denyAccessUnlessGranted('page_edit');

        $success = $this->_runCommand($commandBus, PageResizeColumnCommand::class, [
            'uuid' => $elementUuid,
            'size' => (int) $size,
            'breakpoint' => $breakpoint,
        ], $pageUuid, $onVersion, true);

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => $elementUuid,
            ], 200, $this->_getToolbarRefreshHeaders());
        }

        if (!$success) {
            return $this->_errorResponse();
        }

        return $this->_redirectToPage($pageUuid);
    }

    /**
     * Creates a new section on a Page Aggregate.
     *
     * @Route("/page/create-section/{pageUuid}/{onVersion}/{section}", name="cms_create_section")
     *
     * @param Request $request
     * @param CommandBus $commandBus
     * @param string $pageUuid
     * @param int $onVersion
     * @param string $section
     *
     * @return Response|JsonResponse|RedirectResponse
     * @throws Exception
     */
    public function createSection(Request $request, CommandBus $commandBus, string $pageUuid, int $onVersion, string $section)
    {
        $this->denyAccessUnlessGranted('page_edit');

        $success = $this->_runCommand($commandBus, PageAddElementCommand::class, [
            'elementName' => 'Section',
            'data' => [
                'section' => $section,
            ],
            'parent' => null,
        ], $pageUuid, $onVersion, true);

        if (!$success) {
            return $this->_errorResponse();
        }

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => null, // Refreshes whole page.
            ]);
        }

        return $this->_redirectToPage($pageUuid);
    }
}
