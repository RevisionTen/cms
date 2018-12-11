<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\Common\Collections\ArrayCollection;
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
use RevisionTen\CMS\Model\Website;
use RevisionTen\CMS\Services\CacheService;
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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class MenuController.
 *
 * @Route("/admin")
 */
class MenuController extends AbstractController
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
     * @param bool        $qeue
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

    /**
     * @Route("/edit-menu", name="cms_edit_menu")
     *
     * @param Request                $request
     * @param EntityManagerInterface $entityManager
     * @param AggregateFactory       $aggregateFactory
     *
     * @return Response
     */
    public function editMenu(Request $request, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory): Response
    {
        $config = $this->getParameter('cms');
        $menuUuid = $request->get('uuid');

        // Get menuUuid by read model id.
        if (null === $menuUuid) {
            /** @var int $id MenuRead Id. */
            $id = $request->get('id');
            /** @var MenuRead $menuRead */
            $menuRead = $entityManager->getRepository(MenuRead::class)->find($id);
            if (null === $menuRead) {
                return $this->redirect('/admin');
            }
            $menuUuid = $menuRead->getUuid();
        }

        /** @var Menu $menu */
        $menu = $aggregateFactory->build($menuUuid, Menu::class);

        return $this->render('@cms/Admin/Menu/edit.html.twig', [
            'menu' => $menu,
            'config' => $config,
        ]);
    }

    /**
     * Create a menu.
     *
     * @Route("/menu/create", name="cms_menu_create")
     *
     * @param Request    $request
     * @param CommandBus $commandBus
     * @param MessageBus $messageBus
     *
     * @return JsonResponse|Response
     *
     * @throws \Exception
     */
    public function create(Request $request, CommandBus $commandBus, MessageBus $messageBus)
    {
        /** @var UserRead $user */
        $user = $this->getUser();
        $config = $this->getParameter('cms');
        $currentWebsite = $request->get('currentWebsite');

        $formBuilder = $this->createFormBuilder();

        $formBuilder->add('title', ChoiceType::class, [
            'label' => 'Menu',
            'placeholder' => 'Menu',
            'choices' => array_combine(array_keys($config['menus']), array_keys($config['menus'])),
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('language', ChoiceType::class, [
            'label' => 'Language',
            'placeholder' => 'Language',
            'choices' => $config['page_languages'],
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('submit', SubmitType::class, [
            'label' => 'Add menu',
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
            $success = false;
            $commandBus->dispatch(new MenuCreateCommand($user->getId(), null, $aggregateUuid, 0, $payload, function ($commandBus, $event) use (&$success) {
                // Callback.
                $success = true;
            }));

            return $success ? $this->redirectToMenu($aggregateUuid) : $this->errorResponse($messageBus);
        }

        return $this->render('@cms/Form/form.html.twig', [
            'title' => 'Add menu',
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
     * @throws \Exception
     */
    public function addItem(Request $request, TranslatorInterface $translator, CommandBus $commandBus, MessageBus $messageBus, string $itemName, string $menuUuid, int $onVersion, string $parent = null, array $data = null, string $form_template = '@cms/Form/form.html.twig')
    {
        $config = $this->getParameter('cms');

        if (isset($config['menu_items'][$itemName])) {
            $itemConfig = $config['menu_items'][$itemName];

            /** @var string $formClass */
            $formClass = $itemConfig['class'];
            $implements = class_implements($formClass);

            if ($implements && \in_array(FormTypeInterface::class, $implements, false)) {
                $form = $this->createForm(ElementType::class, ['data' => $data], ['elementConfig' => $itemConfig]);
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $data = $form->getData()['data'];

                    $success = $this->runCommand($commandBus, MenuAddItemCommand::class, [
                        'itemName' => $itemName,
                        'data' => $data,
                        'parent' => $parent,
                    ], $menuUuid, $onVersion, false); // Todo: Qeue events.

                    if ($request->get('ajax')) {
                        return new JsonResponse([
                            'success' => $success,
                            'refresh' => $parent,
                        ]);
                    }

                    return $success ? $this->redirectToMenu($menuUuid) : $this->errorResponse($messageBus);
                }

                return $this->render($form_template, [
                    'title' => $translator->trans('Add %itemName% item', ['%itemName%' => $translator->trans($itemName)]),
                    'form' => $form->createView(),
                ]);
            } else {
                // Not a valid form type.
                throw new InterfaceException($formClass.' must implement '.FormTypeInterface::class);
            }
        } else {
            throw new \Exception('Item type '.$itemName.' does not exist.');
        }
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
     * @throws \Exception
     */
    public function editItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, string $menuUuid, int $onVersion, string $itemUuid, string $form_template = '@cms/Form/form.html.twig')
    {
        /** @var UserRead $user */
        $user = $this->getUser();

        /** @var Menu $aggregate */
        $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());

        if (empty($aggregate->items)) {
            // Aggregate does not exist, or is empty.
            return $this->errorResponse($messageBus);
        }

        // Get the element from the Aggregate.
        $item = MenuBaseHandler::getItem($aggregate, $itemUuid);

        if ($item && isset($item['data'], $item['itemName'])) {
            $data = $item;
            $itemName = $item['itemName'];
            $config = $this->getParameter('cms');

            if (isset($config['menu_items'][$itemName])) {
                $itemConfig = $config['menu_items'][$itemName];
                $formClass = $itemConfig['class'];
                $implements = class_implements($formClass);

                if ($implements && \in_array(FormTypeInterface::class, $implements, false)) {
                    $form = $this->createForm(ElementType::class, $data, ['elementConfig' => $itemConfig]);
                    $form->handleRequest($request);

                    // Get differences in data and check if data has changed.
                    if ($form->isSubmitted()) {
                        $data = $form->getData()['data'];
                        // Remove data that hasn't changed.
                        $data = $this->diff($item['data'], $data);

                        if (empty($data)) {
                            $form->addError(new FormError($translator->trans('Data has not changed.')));
                        }
                    }

                    if ($form->isSubmitted() && $form->isValid()) {
                        $success = $this->runCommand($commandBus, MenuEditItemCommand::class, [
                            'uuid' => $itemUuid,
                            'data' => $data,
                        ], $menuUuid, $onVersion, false); // Todo: Qeue events.

                        if ($request->get('ajax')) {
                            return new JsonResponse([
                                'success' => $success,
                                'refresh' => $itemUuid,
                            ]);
                        }

                        return $success ? $this->redirectToMenu($menuUuid) : $this->errorResponse($messageBus);
                    }

                    return $this->render($form_template, [
                        'title' => $translator->trans('Edit %itemName% item', ['%itemName%' => $translator->trans($itemName)]),
                        'form' => $form->createView(),
                    ]);
                } else {
                    // Not a valid form type.
                    throw new InterfaceException($formClass.' must implement '.FormTypeInterface::class);
                }
            } else {
                throw new \Exception('Item type '.$itemName.' does not exist.');
            }
        } else {
            // Not a valid element.
            throw new \Exception('Item with uuid '.$itemUuid.' is not a valid item.');
        }
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
     */
    public function deleteItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        /** @var UserRead $user */
        $user = $this->getUser();

        /** @var Menu $aggregate */
        $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
        $itemParent = null;
        if (!empty($aggregate->items)) {
            // Get the parent element from the Aggregate.
            MenuBaseHandler::onItem($aggregate, $itemUuid, function ($element, $collection, $parent) use (&$itemParent) {
                $itemParent = $parent['uuid'];
            });
        }

        $success = $this->runCommand($commandBus, MenuRemoveItemCommand::class, [
            'uuid' => $itemUuid,
        ], $menuUuid, $onVersion, false); // Todo: Qeue events.

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
     */
    public function shiftItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid, string $direction)
    {
        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, MenuShiftItemCommand::class, [
            'uuid' => $itemUuid,
            'direction' => $direction,
        ], $menuUuid, $onVersion, false); // Todo: Qeue events.

        if ($request->get('ajax')) {
            // Get the item parent so we know what to refresh.
            /** @var Menu $aggregate */
            $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
            $itemParent = null;
            if (!empty($aggregate->items)) {
                // Get the parent element from the Aggregate.
                MenuBaseHandler::onItem($aggregate, $itemUuid, function ($element, $collection, $parent) use (&$itemParent) {
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
     */
    public function disableItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, MenuDisableItemCommand::class, [
            'uuid' => $itemUuid,
        ], $menuUuid, $onVersion, false); // Todo: Qeue events.

        if ($request->get('ajax')) {
            /** @var Menu $aggregate */
            $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
            $itemParent = null;
            if (!empty($aggregate->items)) {
                // Get the parent element from the Aggregate.
                MenuBaseHandler::onItem($aggregate, $itemUuid, function ($element, $collection, $parent) use (&$itemParent) {
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
     */
    public function enableItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        /** @var UserRead $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, MenuEnableItemCommand::class, [
            'uuid' => $itemUuid,
        ], $menuUuid, $onVersion, false); // Todo: Qeue events.

        if ($request->get('ajax')) {
            /** @var Menu $aggregate */
            $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
            $itemParent = null;
            if (!empty($aggregate->items)) {
                // Get the parent element from the Aggregate.
                MenuBaseHandler::onItem($aggregate, $itemUuid, function ($element, $collection, $parent) use (&$itemParent) {
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
     * @param EntityManagerInterface $entityManager
     * @param int                    $id
     *
     * @return Response
     */
    public function getAlias(EntityManagerInterface $entityManager, int $id): Response
    {
        /** @var Alias $alias */
        $alias = $entityManager->getRepository(Alias::class)->find($id);

        $path = $alias ? $alias->getPath() : '#';

        return new Response($path);
    }

    /**
     * Get all alias ids from items and sub-items.
     *
     * @param array $items
     *
     * @return array
     */
    private function getAliasIds(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $id = $item['data']['alias'] ?? null;
            if ($id) {
                $ids[] = $id;
            }

            if (isset($item['items'])) {
                $ids = array_merge($ids, $this->getAliasIds($item['items']));
            }
        }

        return $ids;
    }

    private function getMenuData(EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, string $name, array $config)
    {
        $menu = null;

        /** @var Menu[] $menuAggregates */
        $menuAggregates = $aggregateFactory->findAggregates(Menu::class);
        foreach ($menuAggregates as $menuAggregate) {
            if ($name === $menuAggregate->name && isset($config['menus'][$menuAggregate->name])) {
                $menu = $config['menus'][$menuAggregate->name];
                $menu['data'] = json_decode(json_encode($menuAggregate), true);
            }
        }

        return $menu;
    }

    private function getPaths(EntityManagerInterface $entityManager, array $items): array
    {
        // Get all aliases.
        $paths = [];

        $aliasIds = $this->getAliasIds($items);
        /** @var Alias[] $aliases */
        $aliases = $entityManager->getRepository(Alias::class)->findBy([
            'id' => $aliasIds,
        ]);
        foreach ($aliases as $alias) {
            if (null !== $alias->getPageStreamRead() && $alias->getPageStreamRead()->isPublished()) {
                $paths[$alias->getId()] = $alias->getPath();
            }
        }

        return $paths;
    }

    /**
     * Renders a menu.
     *
     * @param RequestStack           $requestStack
     * @param CacheService           $cacheService
     * @param EntityManagerInterface $entityManager
     * @param AggregateFactory       $aggregateFactory
     * @param string                 $name
     * @param Alias                  $alias
     * @param string                 $template
     * @param string                 $language
     * @param int                    $website
     *
     * @return Response
     */
    public function renderMenu(RequestStack $requestStack, CacheService $cacheService, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, string $name, Alias $alias = null, string $template = null, string $language = null, int $website = null): Response
    {
        $request = $requestStack->getMasterRequest();
        $config = $this->getParameter('cms');
        if (!isset($config['menus'][$name])) {
            return new Response('Menu '.$name.' does not exist.');
        }

        if (null === $website || null === $language) {
            // Get website and language from alias or request.
            if (null === $alias || null === $alias->getWebsite() || null === $alias->getLanguage()) {
                // Alias does not exist or is neutral, get website and language from request.
                /** @var Website $website */
                $website = $entityManager->getRepository(Website::class)->find($request->get('website'));
                $language = $request->getLocale();
            } else {
                $website = $alias->getWebsite();
                $language = $alias->getLanguage();
            }
        } else {
            /** @var Website $website */
            $website = $entityManager->getRepository(Website::class)->find($website);
        }

        $cacheKey = $name.'_'.$website->getId().'_'.$language;
        $menuData = $cacheService->get($cacheKey);
        if (null === $menuData) {
            /** @var MenuRead $menuRead */
            $menuRead = $entityManager->getRepository(MenuRead::class)->findOneBy([
                'title' => $name,
                'website' => $website,
                'language' => $language,
            ]);

            if (null === $menuRead) {
                $version = 1;
                // No matching read model found, fallback to language neutral menu.
                $menuData = $this->getMenuData($entityManager, $aggregateFactory, $name, $config);
                if (isset($menuData['data']['language'], $menuData['data']['website'])) {
                    // Aggregate isn`t neutral.
                    $menuData = null;
                }
            } else {
                $version = $menuRead->getVersion();
                $menuData = $config['menus'][$name];
                $menuData['data'] = json_decode(json_encode($menuRead->getPayload()), true);
            }

            if ($menuData) {
                // Get paths.
                $menuData['paths'] = empty($menuData['data']['items']) ? [] : $this->getPaths($entityManager, $menuData['data']['items']);

                // Populate cache.
                $cacheService->put($cacheKey, $version, $menuData);
            }
        }



        return $this->render($template ?: $config['menus'][$name]['template'], [
            'request' => $request,
            'alias' => $alias,
            'menu' => $menuData,
            'config' => $config,
        ]);
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
     */
    public function saveOrder(Request $request, TranslatorInterface $translator, CommandBus $commandBus, MessageBus $messageBus, string $menuUuid, int $onVersion)
    {
        $order = json_decode($request->getContent(), true);

        if ($order && isset($order[0])) {
            $order = $order[0];
            $order = ArrayHelpers::cleanOrderTree($order);
        }

        $success = $this->runCommand($commandBus, MenuSaveOrderCommand::class, [
            'order' => $order,
        ], $menuUuid, $onVersion, false); // Todo: Qeue events.

        if (!$success) {
            return $this->errorResponse($messageBus);
        } else {
            $this->addFlash(
                'success',
                $translator->trans('Menu order saved')
            );

            if ($request->get('ajax')) {
                return new JsonResponse([
                    'success' => $success,
                    'refresh' => null,
                ]);
            }
        }

        return $this->redirectToMenu($menuUuid);
    }

    private static function array_diff_recursive(array $arrayOriginal, array $arrayNew): array
    {
        $arrayDiff = [];

        foreach ($arrayOriginal as $key => $value) {
            if (array_key_exists($key, $arrayNew)) {
                if (\is_array($value)) {
                    $arrayRecursiveDiff = self::array_diff_recursive($value, $arrayNew[$key]);
                    if (\count($arrayRecursiveDiff)) {
                        $arrayDiff[$key] = $arrayRecursiveDiff;
                    }
                } elseif ($value !== $arrayNew[$key]) {
                    $arrayDiff[$key] = $value;
                }
            } else {
                $arrayDiff[$key] = $value;
            }
        }

        return $arrayDiff;
    }
}
