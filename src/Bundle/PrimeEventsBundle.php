<?php

namespace Bdf\PrimeEvents\Bundle;

use Bdf\PrimeEvents\Factory\ConsumersFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class PrimeEventsBundle
 */
class PrimeEventsBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container)
            {
                $definition = $container->findDefinition(ConsumersFactory::class);

                foreach ($container->findTaggedServiceIds('prime.events.listener') as $id => $_) {
                    $definition->addMethodCall('register', [new Reference($id)]);
                }
            }
        });
    }
}
