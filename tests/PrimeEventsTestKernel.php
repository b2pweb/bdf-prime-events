<?php

namespace Tests\PrimeEvents;

use Bdf\PrimeEvents\Bundle\PrimeEventsBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class PrimeEventsTestKernel
 */
class PrimeEventsTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param ContainerConfigurator|ContainerBuilder $container
     * @param LoaderInterface|null $loader
     */
    public function configureContainer($container, $loader = null): void
    {
        if ($loader !== null) {
            $loader->load(__DIR__.'/conf.yaml');
        } else {
            $container->import(__DIR__ . '/conf.yaml');
        }
    }

    public function registerBundles()
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Bdf\PrimeBundle\PrimeBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new PrimeEventsBundle(),
        ];
    }

    public function configureRoutes($routes): void
    {
    }
}
