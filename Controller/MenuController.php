<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\Command\MenuAddItemCommand;
use RevisionTen\CMS\Command\MenuCreateCommand;
use RevisionTen\CMS\Command\MenuDisableItemCommand;
use RevisionTen\CMS\Command\MenuEditItemCommand;
use RevisionTen\CMS\Command\MenuEnableItemCommand;
use RevisionTen\CMS\Command\MenuRemoveItemCommand;
use RevisionTen\CMS\Command\MenuShiftItemCommand;
use RevisionTen\CMS\Form\ElementType;
use RevisionTen\CMS\Form\Page;
use RevisionTen\CMS\Handler\MenuBaseHandler;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Model\User;
use RevisionTen\CQRS\Exception\InterfaceException;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
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
class MenuController extends Controller
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
     * Returns info from the messageBus.
     *
     * @return JsonResponse
     */
    public function errorResponse(): JsonResponse
    {
        /** @var MessageBus $messageBus */
        $messageBus = $this->get('messagebus');

        return new JsonResponse($messageBus->getMessagesJson());
    }

    /**
     * Redirects to the edit page of a Menu Aggregate by its uuid.
     *
     * @param string $menuUuid
     *
     * @return Response
     */
    private function redirectToMenu(string $menuUuid): Response
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
     * @param string     $name
     *
     * @return JsonResponse|Response
     *
     * @throws \Exception
     */
    public function create(CommandBus $commandBus, string $name)
    {
        /** @var User $user */
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
                return $this->errorResponse();
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
    public function addItem(Request $request, CommandBus $commandBus, string $itemName, string $menuUuid, int $onVersion, string $parent = null, array $data = null, string $form_template = '@cms/Form/form.html.twig')
    {
        $config = $this->getParameter('cms');

        if (isset($config['menu_items'][$itemName])) {
            $itemConfig = $config['menu_items'][$itemName];

            /** @var string $formClass */
            $formClass = $itemConfig['class'];

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
    public function editItem(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, string $menuUuid, int $onVersion, string $itemUuid, string $form_template = '@cms/Form/form.html.twig')
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var Menu $aggregate */
        $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());

        if (empty($aggregate->items)) {
            // Aggregate does not exist, or is empty.
            return $this->errorResponse();
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
     * @param AggregateFactory $aggregateFactory
     * @param string           $menuUuid
     * @param int              $onVersion
     * @param string           $itemUuid
     *
     * @return JsonResponse|Response
     */
    public function deleteItem(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var Menu $aggregate */
        $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
        $itemParent = null;
        if (!empty($aggregate->elements)) {
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
            return $this->errorResponse();
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
     * @param AggregateFactory $aggregateFactory
     * @param string           $menuUuid
     * @param int              $onVersion
     * @param string           $itemUuid
     * @param string           $direction
     *
     * @return JsonResponse|Response
     */
    public function shiftItem(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid, string $direction)
    {
        /** @var User $user */
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
            return $this->errorResponse();
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
     * @param AggregateFactory $aggregateFactory
     * @param string           $menuUuid
     * @param int              $onVersion
     * @param string           $itemUuid
     *
     * @return JsonResponse|Response
     */
    public function disableItem(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        /** @var User $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, MenuDisableItemCommand::class, [
            'uuid' => $itemUuid,
        ], $menuUuid, $onVersion, false); // Todo: Qeue events.

        if ($request->get('ajax')) {
            /** @var Menu $aggregate */
            $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
            $itemParent = null;
            if (!empty($aggregate->elements)) {
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
            return $this->errorResponse();
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
     * @param AggregateFactory $aggregateFactory
     * @param string           $menuUuid
     * @param int              $onVersion
     * @param string           $itemUuid
     *
     * @return JsonResponse|Response
     */
    public function enableItem(Request $request, CommandBus $commandBus, AggregateFactory $aggregateFactory, string $menuUuid, int $onVersion, string $itemUuid)
    {
        /** @var User $user */
        $user = $this->getUser();

        $success = $this->runCommand($commandBus, MenuEnableItemCommand::class, [
            'uuid' => $itemUuid,
        ], $menuUuid, $onVersion, false); // Todo: Qeue events.

        if ($request->get('ajax')) {
            /** @var Menu $aggregate */
            $aggregate = $aggregateFactory->build($menuUuid, Menu::class, $onVersion, $user->getId());
            $itemParent = null;
            if (!empty($aggregate->elements)) {
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
            return $this->errorResponse();
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
     * @param EntityManagerInterface $entityManager
     * @param AggregateFactory       $aggregateFactory
     * @param string                 $name
     * @param Alias                  $alias
     * @param string                 $template
     *
     * @return Response
     */
    public function renderMenu(RequestStack $requestStack, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, string $name, Alias $alias = null, string $template = null): Response
    {
        $request = $requestStack->getMasterRequest();
        $config = $this->getParameter('cms');
        if (!isset($config['page_menues'][$name])) {
            return new Response('Menu '.$name.' does not exist.');
        }

        $cacheEnabled = extension_loaded('apcu') && ini_get('apc.enabled');

        if ($cacheEnabled) {
            // Cache is enabled.
            $cache = new ApcuAdapter();
            $menu = $cache->getItem($name);

            if ($menu->isHit()) {
                // Get Menu from cache.
                $menuData = $menu->get();
            } else {
                // Get Menu.
                $menuData = $this->getMenuData($entityManager, $aggregateFactory, $name, $config);

                // Visitor triggers a write to cache.
                if ($menuData) {
                    // Persist Menu to cache.
                    $menu->set($menuData);
                    $cache->save($menu);
                }
            }
        } else {
            // Get Menu.
            $menuData = $this->getMenuData($entityManager, $aggregateFactory, $name, $config);
        }

        return $this->render($template ? $template : $menuData['template'], [
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
     * @param string              $name
     *
     * @return RedirectResponse
     *
     * @throws \Exception
     */
    public function clearMenuCache(TranslatorInterface $translator, string $name): RedirectResponse
    {
        $config = $this->getParameter('cms');

        if (!isset($config['page_menues'][$name])) {
            throw new \Exception('Menu does not exist.');
        }

        $cacheEnabled = extension_loaded('apcu') && ini_get('apc.enabled');

        if ($cacheEnabled) {
            // Cache is enabled.
            $cache = new ApcuAdapter();
            $cache->deleteItem($name);

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
}
