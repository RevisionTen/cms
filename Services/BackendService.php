<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class BackendService
{
    protected UrlGeneratorInterface $urlGenerator;

    protected Security $security;

    protected RequestStack $requestStack;

    private array $config;

    public function __construct(UrlGeneratorInterface $urlGenerator, Security $security, RequestStack $requestStack, array $config)
    {
        $this->urlGenerator = $urlGenerator;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->config = $config;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getAdminMenu(): array
    {
        $menu = $this->config['admin_menu'];

        $menu = array_map([$this, 'buildMenuItem'], $menu);
        $menu = $this->removeEmptyMenuItems($menu);

        return $menu;
    }

    private function removeEmptyMenuItems(array $items): array
    {
        $items = array_filter($items, static function ($child) {
            return !empty($child);
        });

        return $items;
    }

    private function buildMenuItem(array $item): ?array
    {
        $path = $item['path'] ?? '#';
        $permissions = $item['permissions'] ?? null;
        if (!empty($permissions)) {
            // Check if user has all permissions.
            foreach ($permissions as $permission) {
                if (!$this->security->isGranted($permission)) {
                    return null;
                }
            }
        }

        $item['path'] = $path;

        $request = $this->requestStack->getMainRequest();
        $websiteId = $request ? $request->get('currentWebsite') : null;
        $route = $request ? $request->get('_route') : null;
        $entity = $request ? $request->query->get('entity') : null;

        if (!empty($item['route'])) {
            $item['active'] = $route === $item['route'];
            $item['path'] = $this->urlGenerator->generate($item['route']);
        } elseif (!empty($item['entity'])) {
            $routeMatches = $route === 'cms_list_entity' || $route === 'cms_edit_entity';
            $item['active'] = $routeMatches && $entity === $item['entity'];
            $item['path'] = $this->urlGenerator->generate('cms_list_entity', [
                'entity' => $item['entity'],
            ]);
        }

        if (!empty($item['children'])) {
            $item['children'] = array_map([$this, 'buildMenuItem'], $item['children']);
            $item['children'] = $this->removeEmptyMenuItems($item['children']);
            if (empty($item['children'])) {
                return null;
            }
        }

        if ($websiteId && !empty($item['websites'])) {
            if (!in_array((int) $websiteId, $item['websites'], true)) {
                return null;
            }
        }

        return $item;
    }
}
