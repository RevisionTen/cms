<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Twig;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Model\MenuRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CMS\Services\CacheService;
use RevisionTen\CQRS\Services\AggregateFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Templating\EngineInterface;
use Twig\Extension\RuntimeExtensionInterface;
use function array_unique;
use function json_decode;
use function json_encode;

class MenuRuntime implements RuntimeExtensionInterface
{
    /** @var \Symfony\Component\HttpFoundation\RequestStack */
    private $requestStack;

    /** @var \RevisionTen\CMS\Services\CacheService */
    private $cacheService;

    /** @var \Doctrine\ORM\EntityManagerInterface */
    private $entityManager;

    /** @var \RevisionTen\CQRS\Services\AggregateFactory */
    private $aggregateFactory;

    /** @var \Symfony\Component\Templating\EngineInterface */
    private $twig;

    /** @var array */
    private $config;

    /**
     * MenuRuntime constructor.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
     * @param \RevisionTen\CMS\Services\CacheService         $cacheService
     * @param \Doctrine\ORM\EntityManagerInterface           $entityManager
     * @param \RevisionTen\CQRS\Services\AggregateFactory    $aggregateFactory
     * @param \Symfony\Component\Templating\EngineInterface  $twig
     * @param array                                          $config
     */
    public function __construct(RequestStack $requestStack, CacheService $cacheService, EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, EngineInterface $twig, array $config)
    {
        $this->requestStack = $requestStack;
        $this->cacheService = $cacheService;
        $this->entityManager = $entityManager;
        $this->aggregateFactory = $aggregateFactory;
        $this->twig = $twig;
        $this->config = $config;
    }

    /**
     * @param array $parameters
     *
     * @return string
     * @throws \Exception
     */
    public function renderMenu(array $parameters): string
    {
        $name = $parameters['name'];
        $alias = $parameters['alias'] ?? null;
        $template = $parameters['template'] ?? null;
        $language = $parameters['language'] ?? null;
        $website = $parameters['website'] ?? null;

        $request = $this->requestStack->getMasterRequest();

        if (!isset($this->config['menus'][$name])) {
            return 'Menu '.$name.' does not exist.';
        }

        if (null === $website || null === $language) {
            // Get website and language from alias or request.
            if (null === $alias || null === $alias->getWebsite() || null === $alias->getLanguage()) {
                // Alias does not exist or is neutral, get website and language from request.
                $websiteId = $request && $request->get('websiteId') ? $request->get('websiteId') : 1;
                /** @var Website|null $website */
                $website = $this->entityManager->getRepository(Website::class)->find($websiteId);

                $language = $request ? $request->getLocale() : null;
                if (null !== $website && null === $language) {
                    $language = $website->getDefaultLanguage();
                }
            } else {
                $website = $alias->getWebsite();
                $language = $alias->getLanguage();
            }
        } else {
            /** @var Website|null $website */
            $website = $this->entityManager->getRepository(Website::class)->find($website);
        }

        $cacheKey = $name.'_'.$website->getId().'_'.$language;
        $menuData = $this->cacheService->get($cacheKey);
        if (null === $menuData) {
            /** @var MenuRead $menuRead */
            $menuRead = $this->entityManager->getRepository(MenuRead::class)->findOneBy([
                'title' => $name,
                'website' => $website,
                'language' => $language,
            ]);

            if (null === $menuRead) {
                $version = 1;
                // No matching read model found, fallback to language neutral menu.
                $menuData = $this->getMenuData($name);
                if (isset($menuData['data']['language'], $menuData['data']['website'])) {
                    // Aggregate isn`t neutral.
                    $menuData = null;
                }
            } else {
                $version = $menuRead->getVersion();
                $menuData = $this->config['menus'][$name];
                $menuData['data'] = json_decode(json_encode($menuRead->getPayload()), true);
            }

            if ($menuData) {
                // Get paths.
                $menuData['paths'] = empty($menuData['data']['items']) ? [] : $this->getPaths($menuData['data']['items'], $website);

                // Populate cache.
                $this->cacheService->put($cacheKey, $version, $menuData);
            }
        }

        return $this->twig->render($template ?: $this->config['menus'][$name]['template'], [
            'request' => $request,
            'alias' => $alias,
            'menu' => $menuData,
            'config' => $this->config,
        ]);
    }

    /**
     * @param string $name
     *
     * @return array|null
     * @throws \Exception
     */
    private function getMenuData(string $name): ?array
    {
        $menu = null;

        /** @var Menu[] $menuAggregates */
        $menuAggregates = $this->aggregateFactory->findAggregates(Menu::class);
        foreach ($menuAggregates as $menuAggregate) {
            if ($name === $menuAggregate->name && isset($config['menus'][$menuAggregate->name])) {
                $menu = $this->config['menus'][$menuAggregate->name];
                $menu['data'] = json_decode(json_encode($menuAggregate), true);
            }
        }

        return $menu;
    }

    /**
     * @param array                               $items
     * @param \RevisionTen\CMS\Model\Website|null $website
     *
     * @return array
     */
    private function getPaths(array $items, ?Website $website = null): array
    {
        // Get all aliases.
        $paths = [];

        $aliasIds = $this->getAliasIds($items);

        /** @var Alias[] $aliases */
        $aliases = $this->entityManager->getRepository(Alias::class)->findBy([
            'id' => $aliasIds,
        ]);

        foreach ($aliases as $alias) {
            if (null !== $alias->getPageStreamRead() && $alias->getPageStreamRead()->isPublished()) {

                $prefix = null !== $website && $website->getDefaultLanguage() !== $alias->getLanguage() ? '/'.$alias->getLanguage() : '';
                $path = $alias->getPath();

                // Do not append / for prefixed front page.
                if ('/' === $path && !empty($prefix)) {
                    $path = '';
                }

                $paths[$alias->getId()] = $prefix.$path;
            }
        }

        return $paths;
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
                $childIds = $this->getAliasIds($item['items']);
                if (!empty($childIds)) {
                    array_push($ids, ...$childIds);
                }
            }
        }

        return array_unique($ids);
    }
}
