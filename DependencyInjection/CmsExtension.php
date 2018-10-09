<?php

namespace RevisionTen\CMS\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
        $config = [];
        foreach ($configs as $subConfig) {
            $config = array_merge($config, $subConfig);
        }

        return $config;
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $config = self::mergeCMSConfig($configs);

        $container->setParameter('cms', $config);
    }

    public function prepend(ContainerBuilder $container)
    {
        // Get configured site name and set the cms.site_name parameter.
        $configs = $container->getExtensionConfig('cms');
        $config = self::mergeCMSConfig($configs);
        $siteName = $config['site_name'] ?? 'CMS';
        $container->setParameter('cms.site_name', $siteName);
        // Get configured languages and set the cms.page_languages parameter.
        $pageLanguages = $config['page_languages'] ?? [
            'English' => 'en',
            'German' => 'de',
            'French' => 'fr',
        ];
        $container->setParameter('cms.page_languages', $pageLanguages);

        // Load the cms bundle config.
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('config.yaml');
        $loader->load('cms.yaml');

        // Only load default security If none exists.
        $fs = new Filesystem();
        if (!$fs->exists($container->getParameter('kernel.project_dir').'/config/packages/security.yaml')) {
            $loader->load('security.yaml');
        }
    }
}
