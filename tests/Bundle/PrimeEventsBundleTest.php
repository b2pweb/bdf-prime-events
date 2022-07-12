<?php

namespace Tests\PrimeEvents\Bundle;

use Bdf\PrimeEvents\Console\ConsumePrimeEvents;
use Bdf\PrimeEvents\Factory\ConsumerConfiguration;
use Bdf\PrimeEvents\Factory\ConsumersFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\HttpKernel\Kernel;
use Tests\PrimeEvents\PrimeEventsTestKernel;
use Tests\PrimeEvents\TestFiles\MyTestEntityListener;

/**
 * Class PrimeEventsBundleTest
 */
class PrimeEventsBundleTest extends TestCase
{
    /**
     *
     */
    public function test_services()
    {
        $kernel = new PrimeEventsTestKernel('dev', false);
        $kernel->boot();

        $this->assertInstanceOf(ConsumersFactory::class, $kernel->getContainer()->get(ConsumersFactory::class));
    }

    /**
     * @throws \ReflectionException
     */
    public function test_autoconfigure_listeners()
    {
        $kernel = new PrimeEventsTestKernel('dev', false);
        $kernel->boot();

        $factory = $kernel->getContainer()->get(ConsumersFactory::class);

        $r = (new \ReflectionClass($factory))->getProperty('listeners');
        $r->setAccessible(true);

        $this->assertInstanceOf(MyTestEntityListener::class, $r->getValue($factory)[0]);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_config()
    {
        $kernel = new PrimeEventsTestKernel('dev', false);
        $kernel->boot();

        $factory = $kernel->getContainer()->get(ConsumersFactory::class);

        $r = (new \ReflectionClass($factory))->getProperty('config');
        $r->setAccessible(true);

        $this->assertEquals(['other' => new ConsumerConfiguration([
            'user' => 'other_user',
            'password' => 'other_pass',
            'logPositionFile' => dirname(dirname(__DIR__)).'/var/events',
        ])], $r->getValue($factory));
    }

    /**
     *
     */
    public function test_console()
    {
        $kernel = new PrimeEventsTestKernel('dev', false);
        $kernel->boot();

        $console = new Application($kernel);

        if (PHP_MAJOR_VERSION < 8) {
            $this->assertInstanceOf(ConsumePrimeEvents::class, $console->get('prime:events:consume'));
        } else {
            $this->assertInstanceOf(LazyCommand::class, $console->get('prime:events:consume'));
            $this->assertInstanceOf(ConsumePrimeEvents::class, $console->get('prime:events:consume')->getCommand());
        }
    }
}
