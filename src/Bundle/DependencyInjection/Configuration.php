<?php

namespace Bdf\PrimeEvents\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('prime_events');
        $treeBuilder->getRootNode()
            ->useAttributeAsKey('connection')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('user')->end()
                    ->scalarNode('password')->end()
                    ->scalarNode('charset')->end()
                    ->scalarNode('gtid')->end()
                    ->integerNode('slaveId')->end()
                    ->scalarNode('mariaDbGtid')->end()
                    ->integerNode('tableCacheSize')->end()
                    ->floatNode('heartbeatPeriod')->end()
                    ->scalarNode('logPositionFile')->end()
                ->end()
                ->beforeNormalization()->castToArray()
            ->end()
        ;

        return $treeBuilder;
    }
}
