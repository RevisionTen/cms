<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Exception;
use InvalidArgumentException;
use RevisionTen\CMS\Command\MenuAddItemCommand;
use RevisionTen\CMS\Command\MenuCreateCommand;
use RevisionTen\CMS\Command\MenuDisableItemCommand;
use RevisionTen\CMS\Command\MenuEditItemCommand;
use RevisionTen\CMS\Command\MenuEnableItemCommand;
use RevisionTen\CMS\Command\MenuRemoveItemCommand;
use RevisionTen\CMS\Command\MenuSaveOrderCommand;
use RevisionTen\CMS\Command\MenuShiftItemCommand;
use RevisionTen\CMS\Form\ElementType;
use RevisionTen\CMS\Handler\MenuBaseHandler;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Model\MenuRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\Twig\MenuRuntime;
use RevisionTen\CMS\Utilities\ArrayHelpers;
use RevisionTen\CQRS\Exception\InterfaceException;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use function array_combine;
use function array_keys;
use function class_implements;
use function in_array;
use function json_decode;
use function sprintf;
use function trigger_error;

/**
 * @Route("/admin")
 */
class MenuController extends AbstractController
{
    /**
     * Returns info from the messageBus.
     *
     * @param MessageBus $messageBus
     *
     * @return JsonResponse
     */
    public function errorResponse(MessageBus $messageBus): JsonResponse
    {
        return new JsonResponse($messageBus->getMessagesJson());
    }

    /**
     * @Route("/edit-menu", name="cms_edit_menu")
     *
     * @param Request $request
     * @param PageService $pageService
     * @param EntityManagerInterface $entityManager
     * @param AggregateFactory $aggregateFactory
     *
     * @return Response
     */
    public function editMenu(Request $request, PageService $pageService, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory): Response
    {
        $this->denyAccessUnlessGranted('menu_edit');

        $config = $this->getParameter('cms');
        $menuUuid = $request->get('uuid');

        // Get menuUuid by read model id.
        if (null === $menuUuid) {
            /**
             * @var int $id MenuRead Id.
             */
            $id = $request->get('id');
            /**
             * @var MenuRead $menuRead
             */
            $menuRead = $entityManager->getRepository(MenuRead::class)->find($id);
            if (null === $menuRead) {
                return $this->redirect('/admin');
            }
            $menuUuid = $menuRead->getUuid();
        }

        /**
         * @var Menu $menu
         */
        $menu = $aggregateFactory->build($menuUuid, Menu::class);

        if (!empty($menu->items)) {
            $menu->items = $pageService->hydratePage($menu->items);
        }

        return $this->render('@CMS/Backend/Menu/edit.html.twig', [
            'menu' => $menu,
            'config' => $config,
        ]);
    }

    /**
     * Create a menu.
     *
     * @Route("/menu/create", name="cms_menu_create")
     *
     * @param Request             $request
     * @param CommandBus          $commandBus
     * @param MessageBus          $messageBus
     * @param TranslatorInterface $translator
     *
     * @return JsonResponse|Response
     *
     * @throws Exception
     */
    public function create(Request $request, CommandBus $commandBus, MessageBus $messageBus, TranslatorInterface $translator)
    {
        $this->denyAccessUnlessGranted('menu_create');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();
        $config = $this->getParameter('cms');
        $currentWebsite = $request->get('currentWebsite');

        $formBuilder = $this->createFormBuilder();

        // Filter menus by website.
        $menus = array_filter($config['menus'], static function ($menu) use ($currentWebsite) {
            return empty($menu['websites']) || (is_array($menu['websites']) && in_array($currentWebsite, $menu['websites']));
        });
        $menus = array_combine(array_keys($menus), array_keys($menus));

        $formBuilder->add('title', ChoiceType::class, [
            'label' => 'admin.label.menu',
            'translation_domain' => 'cms',
            'placeholder' => '',
            'choices' => $menus,
            'choice_translation_domain' => 'messages',
            'constraints' => new NotBlank(),
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $formBuilder->add('language', ChoiceType::class, [
            'label' => 'admin.label.language',
            'translation_domain' => 'cms',
            'placeholder' => 'admin.label.language',
            'choice_translation_domain' => 'messages',
            'choices' => $config['page_languages'],
            'constraints' => new NotBlank(),
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $formBuilder->add('save', SubmitType::class, [
            'label' => 'admin.btn.addMenu',
            'translation_domain' => 'cms',
            'attr' => [
                'class' => 'btn-primary',
            ],
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $payload = [
                'name' => $data['title'],
                'website' => (int) $currentWebsite,
                'language' => $data['language'],
            ];

            $aggregateUuid = Uuid::uuid1()->toString();

            // Execute Command.
            $success = $commandBus->dispatch(new MenuCreateCommand(
                $user->getId(),
                null,
                $aggregateUuid,
                0,
                $payload
            ));

            return $success ? $this->redirectToMenu($aggregateUuid) : $this->errorResponse($messageBus);
        }

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.btn.addMenu', [], 'cms'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/menu/add/{itemName}/{menuUuid}/{onVersion}/{parent}", name="cms_menu_additem")
     *
     * @param Request             $request
     * @param TranslatorInterface $translator
     * @param CommandBus          $commandBus
     * @param MessageBus          $messageBus
     * @param string              $itemName
     * @param string              $menuUuid
     * @param int                 $onVersion
     * @param string|null         $parent
     * @param array|null          $data
     * @param string              $form_template
     *
     * @return JsonResponse|Response
     *
     * @throws InterfaceException
     * @throws Exception
     */
    public function addItem(Request $request, TranslatorInterface $translator, CommandBus $commandBus, MessageBus $messageBus, string $itemName, string $menuUuid, int $onVersion, string $parent = null, array $data = null, string $form_template = '@CMS/Backend/Form/form.html.twig')
    {
        $this->denyAccessUnlessGranted('menu_edit');

        $config = $this->getParameter('cms');

        $itemExists = isset($config['menu_items'][$itemName]);

        if (!$itemExists) {
            throw new InvalidArgumentException('Item type '.$itemName.' does not exist.');
        }

        $itemConfig = $config['menu_items'][$itemName];

        // Check if item belongs to currentWebsite.
        $currentWebsite = (int) $request->get('currentWebsite');
        $websites = $itemConfig['websites'] ?? null;
        if ($websites && !in_array($currentWebsite, $websites, true)) {
            throw new AccessDeniedHttpException();
        }

        /**
         * @var string $formClass
         */
        $formClass = $itemConfig['class'];
        $implements = class_implements($formClass);
        $isValidFormType = $implements && in_array(FormTypeInterface::class, $implements, false);

        if (!$isValidFormType) {
            // Not a valid form type.
            throw new InterfaceException($formClass.' must implement '.FormTypeInterface::class);
        }

        $form = $this->createForm(ElementType::class, ['data' => $data], ['elementConfig' => $itemConfig]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData()['data'];

            $success = $this->runCommand($commandBus, MenuAddItemCommand::class, [
                'itemName' => $itemName,
                'data' => $data,
                'parent' => $parent,
            ], $menuUuid, $onVersion); // Todo: Qeue events.

            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                    'refresh' => $parent,
                ]);
            }

            return $success ? $this->redirectToMenu($menuUuid) : $this->errorResponse($messageBus);
        }

        return $this->render($form_template, [
            'title' => $translator->trans('admin.btn.addMenuItem', ['%itemName%' => $translator->trans($itemName)], 'cms'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays the edit form for a element.
     *
     * @Route("/menu/edit-item/{menuUuid}/{onVersion}/{itemUuid}", name="cms_menu_edititem")
     *
     * @param Request             $request
     * @param CommandBus          $commandBus
     * @param MessageBus          $messageBus
     * @param AggregateFactory    $aggregateFactory
     * @param TranslatorInterface $translator
     * @param string              $menuUuid
     * @param int                 $onVersion
     * @param string              $itemUuid
     * @param string              $form_template
     *
     * @return JsonResponse|Response
     *
     * @throws InterfaceException
     * @throws Exception
     */
    public function editItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, string $menuUuid, int $onVersion, string $itemUuid, string $form_template = '@CMS/Backend/Form/form.html.twig')
    {
        $this->denyAccessUnlessGranted('menu_edit');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        /**
         * @var Menu $aggregate
         */
        $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());

        if (empty($aggregate->items)) {
            // Aggregate does not exist, or is empty.
            return $this->errorResponse($messageBus);
        }

        // Get the element from the Aggregate.
        $item = MenuBaseHandler::getItem($aggregate, $itemUuid);

        $isValidItem = $item && isset($item['data'], $item['itemName']);
        if (!$isValidItem) {
            // Not a valid item.
            throw new InvalidArgumentException('Item with uuid '.$itemUuid.' is not a valid item.');
        }

        $data = $item;
        $itemName = $item['itemName'];
        $config = $this->getParameter('cms');

        $itemExists = isset($config['menu_items'][$itemName]);
        if (!$itemExists) {
            throw new InvalidArgumentException('Item type '.$itemName.' does not exist.');
        }

        $itemConfig = $config['menu_items'][$itemName];

        // Check if item belongs to currentWebsite.
        $currentWebsite = (int) $request->get('currentWebsite');
        $websites = $itemConfig['websites'] ?? null;
        if ($websites && !in_array($currentWebsite, $websites, true)) {
            throw new AccessDeniedHttpException();
        }

        $formClass = $itemConfig['class'];
        $implements = class_implements($formClass);

        $isValidFormType = $implements && in_array(FormTypeInterface::class, $implements, false);
        if (!$isValidFormType) {
            // Not a valid form type.
            throw new InterfaceException($formClass.' must implement '.FormTypeInterface::class);
        }

        $form = $this->createForm(ElementType::class, $data, ['elementConfig' => $itemConfig]);
        $form->handleRequest($request);

        // Get differences in data and check if data has changed.
        if ($form->isSubmitted()) {
            $data = $form->getData()['data'];
            // Remove data that hasn't changed.
            $data = ArrayHelpers::diff($item['data'], $data);

            if (empty($data)) {
                $form->addError(new FormError($translator->trans('admin.validation.dataUnchanged', [], 'cms')));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $success = $this->runCommand($commandBus, MenuEditItemCommand::class, [
                'uuid' => $itemUuid,
                'data' => $data,
            ], $menuUuid, $onVersion); // Todo: Queue events.

            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                    'refresh' => $itemUuid,
                ]);
            }

            return $success ? $this->redirectToMenu($menuUuid) : $this->errorResponse($messageBus);
        }

        return $this->render($form_template, [
            'title' => $translator->trans('admin.btn.editMenuItem', ['%itemName%' => $translator->trans($itemName)], 'cms'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a item from a Menu Aggregate.
     *
     * @Route("/menu/delete-item/{menuUuid}/{onVersion}/{itemUuid}", name="cms_menu_deleteitem")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param MessageBus       $messageBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $menuUuid
     * @param int              $onVersion
     * @param string           $itemUuid
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function deleteItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        $this->denyAccessUnlessGranted('menu_edit');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        /**
         * @var Menu $aggregate
         */
        $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
        $itemParent = null;
        if (!empty($aggregate->items)) {
            // Get the parent element from the Aggregate.
            MenuBaseHandler::onItem($aggregate, $itemUuid, static function ($element, $collection, $parent) use (&$itemParent) {
                $itemParent = $parent['uuid'] ?? null;
            });
        }

        $success = $this->runCommand($commandBus, MenuRemoveItemCommand::class, [
            'uuid' => $itemUuid,
        ], $menuUuid, $onVersion); // Todo: Queue events.

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => $itemParent,
            ]);
        }

        if (!$success) {
            return $this->errorResponse($messageBus);
        }

        return $this->redirectToMenu($menuUuid);
    }

    /**
     * Shift an item up or down on a Menu Aggregate.
     *
     * @Route("/menu/shift-item/{menuUuid}/{onVersion}/{itemUuid}/{direction}", name="cms_menu_shiftitem")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param MessageBus       $messageBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $menuUuid
     * @param int              $onVersion
     * @param string           $itemUuid
     * @param string           $direction
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function shiftItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid, string $direction)
    {
        $this->denyAccessUnlessGranted('menu_edit');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, MenuShiftItemCommand::class, [
            'uuid' => $itemUuid,
            'direction' => $direction,
        ], $menuUuid, $onVersion); // Todo: Queue events.

        if ($request->get('ajax')) {
            // Get the item parent so we know what to refresh.
            /**
             * @var Menu $aggregate
             */
            $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
            $itemParent = null;
            if (!empty($aggregate->items)) {
                // Get the parent element from the Aggregate.
                MenuBaseHandler::onItem($aggregate, $itemUuid, static function ($element, $collection, $parent) use (&$itemParent) {
                    $itemParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $itemParent,
            ]);
        }

        if (!$success) {
            return $this->errorResponse($messageBus);
        }

        return $this->redirectToMenu($menuUuid);
    }

    /**
     * Disables an item.
     *
     * @Route("/menu/disable-item/{menuUuid}/{onVersion}/{itemUuid}", name="cms_menu_disableitem")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param MessageBus       $messageBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $menuUuid
     * @param int              $onVersion
     * @param string           $itemUuid
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function disableItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        $this->denyAccessUnlessGranted('menu_edit');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, MenuDisableItemCommand::class, [
            'uuid' => $itemUuid,
        ], $menuUuid, $onVersion); // Todo: Qeue events.

        if ($request->get('ajax')) {
            /**
             * @var Menu $aggregate
             */
            $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
            $itemParent = null;
            if (!empty($aggregate->items)) {
                // Get the parent element from the Aggregate.
                MenuBaseHandler::onItem($aggregate, $itemUuid, static function ($element, $collection, $parent) use (&$itemParent) {
                    $itemParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $itemParent,
            ]);
        }

        if (!$success) {
            return $this->errorResponse($messageBus);
        }

        return $this->redirectToMenu($menuUuid);
    }

    /**
     * Enables an item.
     *
     * @Route("/menu/enable-item/{menuUuid}/{onVersion}/{itemUuid}", name="cms_menu_enableitem")
     *
     * @param Request          $request
     * @param CommandBus       $commandBus
     * @param MessageBus       $messageBus
     * @param AggregateFactory $aggregateFactory
     * @param string           $menuUuid
     * @param int              $onVersion
     * @param string           $itemUuid
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function enableItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        $this->denyAccessUnlessGranted('menu_edit');

        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, MenuEnableItemCommand::class, [
            'uuid' => $itemUuid,
        ], $menuUuid, $onVersion); // Todo: Qeue events.

        if ($request->get('ajax')) {
            /**
             * @var Menu $aggregate
             */
            $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
            $itemParent = null;
            if (!empty($aggregate->items)) {
                // Get the parent element from the Aggregate.
                MenuBaseHandler::onItem($aggregate, $itemUuid, static function ($element, $collection, $parent) use (&$itemParent) {
                    $itemParent = $parent['uuid'];
                });
            }

            return new JsonResponse([
                'success' => $success,
                'refresh' => $itemParent,
            ]);
        }

        if (!$success) {
            return $this->errorResponse($messageBus);
        }

        return $this->redirectToMenu($menuUuid);
    }

    /**
     * Gets an alias path from the alias id.
     *
     * @deprecated since 2.0.8, do not use this anymore.
     *
     * @param EntityManagerInterface $entityManager
     * @param int                    $id
     *
     * @return Response
     */
    public function getAlias(EntityManagerInterface $entityManager, int $id): Response
    {
        @trigger_error(sprintf('The "MenuController::getAlias" function is deprecated since 2.0.8, do not use it anymore.'), E_USER_DEPRECATED);

        /**
         * @var Alias $alias
         */
        $alias = $entityManager->getRepository(Alias::class)->find($id);

        $path = $alias ? $alias->getPath() : '#';

        return new Response($path);
    }

    /**
     * Renders a menu.
     *
     * @param MenuRuntime $menuRuntime
     * @param string      $name
     * @param Alias|null  $alias
     * @param string|null $template
     * @param string|null $language
     * @param int|null    $website
     *
     * @return Response
     * @throws Exception
     * @deprecated since 2.0.8, use 'cms_menu()' in your template instead.
     *
     */
    public function renderMenu(MenuRuntime $menuRuntime, string $name, Alias $alias = null, string $template = null, string $language = null, int $website = null): Response
    {
        @trigger_error(sprintf('Rendering the "MenuController::renderMenu" function in your template is deprecated since 2.0.8, use the Twig extension function "cms_menu()" in your template instead.'), E_USER_DEPRECATED);

        $renderedMenu = $menuRuntime->renderMenu([
            'name' => $name,
            'alias' => $alias,
            'template' => $template,
            'language' => $language,
            'website' => $website,
        ]);

        return new Response($renderedMenu);
    }

    /**
     * @Route("/menu/save-order/{menuUuid}/{onVersion}", name="cms_menu_saveorder")
     *
     * @param Request             $request
     * @param TranslatorInterface $translator
     * @param CommandBus          $commandBus
     * @param MessageBus          $messageBus
     * @param string              $menuUuid
     * @param int                 $onVersion
     *
     * @return JsonResponse|RedirectResponse
     * @throws Exception
     */
    public function saveOrder(Request $request, TranslatorInterface $translator, CommandBus $commandBus, MessageBus $messageBus, string $menuUuid, int $onVersion)
    {
        $this->denyAccessUnlessGranted('menu_edit');

        $order = json_decode($request->getContent(), true);

        if ($order && isset($order[0])) {
            $order = $order[0];
            $order = ArrayHelpers::cleanOrderTree($order);
        }

        $success = $this->runCommand($commandBus, MenuSaveOrderCommand::class, [
            'order' => $order,
        ], $menuUuid, $onVersion); // Todo: Qeue events.

        if (!$success) {
            return $this->errorResponse($messageBus);
        }

        $this->addFlash(
            'success',
            $translator->trans('admin.label.savedMenuOrder', [], 'cms')
        );

        if ($request->get('ajax')) {
            return new JsonResponse([
                'success' => $success,
                'refresh' => null,
            ]);
        }

        return $this->redirectToMenu($menuUuid);
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
     * Redirects to the edit page of a Menu Aggregate by its uuid.
     *
     * @param string $menuUuid
     *
     * @return RedirectResponse
     */
    private function redirectToMenu(string $menuUuid): RedirectResponse
    {
        return $this->redirectToRoute('cms_edit_menu', [
            'uuid' => $menuUuid,
        ]);
    }
}
