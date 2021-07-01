<?php

namespace Bdf\PrimeEvents\Bundle\DependencyInjection;

use Bdf\PrimeEvents\Factory\ConsumersFactory;
use Bdf\PrimeEvents\Factory\EntityEventsListenerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class PrimeEventsExtension
 */
class PrimeEventsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('prime_events.yaml');

        $container->registerForAutoconfiguration(EntityEventsListenerInterface::class)
            ->addTag('prime.events.listener')
            ->setPublic(true)
        ;

        $factoryDefinition = $container->findDefinition(ConsumersFactory::class);

        foreach ($config as $connection => $consumerConfig) {
            $factoryDefinition->addMethodCall('configure', [$connection, $consumerConfig]);
        }
    }
}
