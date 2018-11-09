<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

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
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Services\CacheService;
use RevisionTen\CMS\Utilities\ArrayHelpers;
use RevisionTen\CQRS\Exception\InterfaceException;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;

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
        return $this->redirectToRoute('cms_list_menues');
    }

    /**
     * @Route("/list-menues", name="cms_list_menues")
     *
     * @param AggregateFactory $aggregateFactory
     *
     * @return Response
     */
    public function listMenues(AggregateFactory $aggregateFactory): Response
    {
        $config = $this->getParameter('cms');

        /** @var Menu[] $menues */
        $menues = $aggregateFactory->findAggregates(Menu::class);

        $missingMenues = $config['page_menues'];
        foreach ($menues as $menu) {
            if (isset($missingMenues[$menu->name])) {
                unset($missingMenues[$menu->name]);
            }
        }

        return $this->render('@cms/Admin/list-menues.html.twig', [
            'menues' => $menues,
            'missingMenues' => $missingMenues,
            'config' => $config,
        ]);
    }

    /**
     * Create a menu.
     *
     * @Route("/menu/create/{name}", name="cms_menu_create")
     *
     * @param CommandBus $commandBus
     * @param MessageBus $messageBus
     * @param string     $name
     *
     * @return JsonResponse|Response
     *
     * @throws \Exception
     */
    public function create(CommandBus $commandBus, MessageBus $messageBus, string $name)
    {
        /** @var UserRead $user */
        $user = $this->getUser();

        $config = $this->getParameter('cms');

        if (isset($config['page_menues'][$name])) {
            $data = [
                'name' => $name,
            ];

            $uuid = Uuid::uuid1()->toString();
            $aggregateUuid = Uuid::uuid1()->toString();

            // Execute Command.
            $success = false;

            $commandBus->dispatch(new MenuCreateCommand($user->getId(), $uuid, $aggregateUuid, 0, $data, function ($commandBus, $event) use (&$success) {
                // Callback.
                $success = true;
            }));

            if ($success) {
                return $this->redirectToMenu($aggregateUuid);
            } else {
                return $this->errorResponse($messageBus);
            }
        } else {
            throw new \Exception('Menu with the name '.$name.' is not defined in the cms config');
        }
    }

    /**
     * @Route("/menu/add/{itemName}/{menuUuid}/{onVersion}/{parent}", name="cms_menu_additem")
     *
     * @param Request     $request
     * @param CommandBus  $commandBus
     * @param MessageBus  $messageBus
     * @param string      $itemName
     * @param string      $menuUuid
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
    public function addItem(Request $request, CommandBus $commandBus, MessageBus $messageBus, string $itemName, string $menuUuid, int $onVersion, string $parent = null, array $data = null, string $form_template = '@cms/Form/form.html.twig')
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

                    if ($success) {
                        return $this->redirectToMenu($menuUuid);
                    } else {
                        return $this->errorResponse($messageBus);
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

        if ($item && isset($item['data']) && isset($item['itemName'])) {
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

                        if ($success) {
                            return $this->redirectToMenu($menuUuid);
                        } else {
                            return $this->errorResponse($messageBus);
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
            if ($name === $menuAggregate->name && isset($config['page_menues'][$menuAggregate->name])) {
                $menu = $config['page_menues'][$menuAggregate->name];
                $menu['data'] = json_decode(json_encode($menuAggregate), true);
            }
        }

        // Get all aliases.
        $paths = [];
        $items = $menu['data']['items'];
        if ($items) {
            $aliasIds = $this->getAliasIds($items);
            /** @var Alias[] $aliases */
            $aliases = $entityManager->getRepository(Alias::class)->findBy([
                'id' => $aliasIds,
            ]);
            foreach ($aliases as $alias) {
                if ($alias->getPageStreamRead()->isPublished()) {
                    $paths[$alias->getId()] = $alias->getPath();
                }
            }
        }
        $menu['paths'] = $paths;

        return $menu;
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
     *
     * @return Response
     */
    public function renderMenu(RequestStack $requestStack, CacheService $cacheService, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, string $name, Alias $alias = null, string $template = null): Response
    {
        $request = $requestStack->getMasterRequest();
        $config = $this->getParameter('cms');
        if (!isset($config['page_menues'][$name])) {
            return new Response('Menu '.$name.' does not exist.');
        }

        $menuData = $cacheService->get($name);

        if (null === $menuData) {
            $menuData = $this->getMenuData($entityManager, $aggregateFactory, $name, $config);

            if ($menuData) {
                // Populate cache.
                $cacheService->put($name, 1, $menuData);
            }
        }

        return $this->render($template ?: $config['page_menues'][$name]['template'], [
            'request' => $request,
            'alias' => $alias,
            'menu' => $menuData,
            'config' => $config,
        ]);
    }

    /**
     * Clear the cache for a menu.
     *
     * @Route("/menu/clear-cache/{name}", name="cms_menu_clearmenucache")
     *
     * @param TranslatorInterface $translator
     * @param CacheService        $cacheService
     * @param string              $name
     *
     * @return RedirectResponse
     *
     * @throws \Exception
     */
    public function clearCache(TranslatorInterface $translator, CacheService $cacheService, string $name): RedirectResponse
    {
        $config = $this->getParameter('cms');

        if (!isset($config['page_menues'][$name])) {
            throw new \Exception('Menu does not exist.');
        }

        if ($cacheService->delete($name, 1)) {
            $this->addFlash(
                'success',
                $translator->trans('Menu cache cleared.')
            );
        } else {
            $this->addFlash(
                'danger',
                $translator->trans('Cache is not enabled.')
            );
        }

        return $this->redirectToRoute('cms_list_menues');
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
