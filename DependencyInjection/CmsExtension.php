<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DependencyInjection;

use Exception;
use RevisionTen\CMS\CmsBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function array_merge;
use function array_reverse;
use function ftok;

class CmsExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Merge all cms configs in reverse order.
     * First (user defined) config is most important.
     *
     * @param array $configs
     *
     * @return array
     */
    private static function mergeCMSConfig(array $configs): array
    {
        $configs = array_reverse($configs);

        $permissions = [];
        $page_elements = [];
        $admin_menu = [];
        $config = [];
        foreach ($configs as $subConfig) {
            $config = array_merge($config, $subConfig);

            // Aggregate all permissions, dont override.
            if (isset($subConfig['permissions'])) {
                $permissions = array_merge($permissions, $subConfig['permissions']);
            }
            // Aggregate all page elements, dont override.
            if (isset($subConfig['page_elements'])) {
                $page_elements = array_merge($page_elements, $subConfig['page_elements']);
            }
            // Aggregate all page elements, dont override.
            if (isset($subConfig['admin_menu'])) {
                $admin_menu = array_merge_recursive($admin_menu, $subConfig['admin_menu']);
            }
        }

        $config['permissions'] = $permissions;
        $config['page_elements'] = $page_elements;
        $config['admin_menu'] = $admin_menu;

        // Use deprecated "page_menues" config If it is set.
        if (!empty($config['page_menues'])) {
            $config['menus'] = $config['page_menues'];
            unset($config['page_menues']);
        }

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = self::mergeCMSConfig($configs);

        // Use the license file to generate the key.
        $config['shm_key'] = ftok(__DIR__.'/../LICENSE', 'c');

        $container->setParameter('cms', $config);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function prepend(ContainerBuilder $container): void
    {
        $fs = new Filesystem();
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        // Load default cms config.
        $loader->load('cms.yaml');
        $loader->load('permissions.yaml');

        // Get configured site name and set the cms.site_name parameter.
        $configs = $container->getExtensionConfig('cms');
        $config = self::mergeCMSConfig($configs);

        // Get site name.
        $siteName = $config['site_name'] ?? 'CMS';
        // Get configured languages and set the cms.page_languages parameter.
        $pageLanguages = $config['page_languages'] ?? [
            'English' => 'en',
            'German' => 'de',
            'French' => 'fr',
        ];
        // Build admin menu config.
        $adminMenu = [
            ['label' => 'admin.label.dashboard', 'route' => 'cms_dashboard', 'icon' => 'nope fas fa-tachometer-alt'],
        ];
        $adminMenu[] = ['label' => 'admin.label.content'];
        if (isset($config['admin_menu']['Content'])) {
            foreach ($config['admin_menu']['Content'] as $menuItem) {
                $adminMenu[] = $menuItem;
            }
        }
        $adminMenu[] = ['label' => 'admin.label.structure'];
        if (isset($config['admin_menu']['Structure'])) {
            foreach ($config['admin_menu']['Structure'] as $menuItem) {
                $adminMenu[] = $menuItem;
            }
        }
        $adminMenu[] = ['label' => 'admin.label.settings'];
        if (isset($config['admin_menu']['Settings'])) {
            foreach ($config['admin_menu']['Settings'] as $menuItem) {
                $adminMenu[] = $menuItem;
            }
        }
        $adminMenu[] = ['label' => 'admin.label.systemInformation', 'route' => 'cms_systeminfo', 'icon' => 'nope fas fa-info-circle'];

        // Set parameters that are used in other configurations.
        $container->setParameter('cms.version', CmsBundle::VERSION);
        $container->setParameter('cms.site_name', $siteName);
        $container->setParameter('cms.page_languages', $pageLanguages);
        $container->setParameter('cms.admin_menu', $adminMenu);
        $container->setParameter('cms.page_templates', $config['page_templates']); // Needed to check permissions in admin templates.

        // Load the cms bundle config.
        $loader->load('services.yaml');
        $loader->load('config.yaml');

        // Only load default security If none exists.
        if (!$fs->exists($container->getParameter('kernel.project_dir').'/config/packages/security.yaml')) {
            $loader->load('security.yaml');
        }
    }
}
