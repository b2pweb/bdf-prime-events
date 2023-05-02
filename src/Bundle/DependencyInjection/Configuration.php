<?php

namespace Bdf\PrimeEvents\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     *
     * @psalm-suppress PossiblyNullReference
     * @psalm-suppress PossiblyUndefinedMethod
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('prime_events');
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();

        $root
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
