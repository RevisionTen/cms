<?php

namespace RevisionTen\CMS\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('cms');
        $rootNode
            ->children()
                ->scalarNode('mailer_from')->end()
                ->scalarNode('mailer_sender')->end()
                ->scalarNode('mailer_return_path')->end()
                ->scalarNode('page_metatype')->end()
                ->arrayNode('page_elements')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->end()
                            ->scalarNode('template')->end()
                            ->scalarNode('icon')->end()
                            ->booleanNode('public')->end()
                            ->arrayNode('children')
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode('styles')
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('menu_items')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->end()
                            ->scalarNode('template')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('page_languages')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('page_menues')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('template')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('page_templates')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('template')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('controller')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('action')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}