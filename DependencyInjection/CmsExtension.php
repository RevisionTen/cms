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
    public function load(array $configs, ContainerBuilder $container)
    {
        $configs = array_reverse($configs);
        $config = [];
        foreach ($configs as $subConfig) {
            $config = array_merge($config, $subConfig);
        }

        $container->setParameter('cms', $config);
    }

    public function prepend(ContainerBuilder $container)
    {
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
