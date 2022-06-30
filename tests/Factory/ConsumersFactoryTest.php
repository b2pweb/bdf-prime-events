<?php

namespace Tests\PrimeEvents\Factory;

use Bdf\Prime\Events;
use Bdf\Prime\Prime;
use Bdf\Prime\ServiceLocator;
use Bdf\PrimeEvents\EntityEventsConsumer;
use Bdf\PrimeEvents\EntityEventsListener;
use Bdf\PrimeEvents\Factory\ConsumersFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Tests\PrimeEvents\PrimeEventsTestKernel;
use Tests\PrimeEvents\TestFiles\MyTestEntity;
use Tests\PrimeEvents\TestFiles\MyTestEntityListener;
use Tests\PrimeEvents\TestFiles\OtherEntity;
use Tests\PrimeEvents\TestFiles\OtherEntityListener;

class ConsumersFactoryTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;

    protected function setUp(): void
    {
        $kernel = new PrimeEventsTestKernel('dev', false);
        $kernel->boot();

        $this->container = $kernel->getContainer();
        Prime::configure($this->container->get(ServiceLocator::class));
        MyTestEntity::repository()->schema()->migrate();
        OtherEntity::repository()->schema()->migrate();
    }

    protected function tearDown(): void
    {
        MyTestEntity::repository()->schema()->drop();
        OtherEntity::repository()->schema()->drop();
        Prime::configure(null);
    }

    /**
     *
     */
    public function test_forConnection_functional()
    {
        /** @var EntityEventsConsumer $consumer */
        $consumer = $this->container->get(ConsumersFactory::class)->forConnection('test');
        $consumer->start();

        $entity = new MyTestEntity(['value' => 'Foo']);
        $entity->insert();

        while (!MyTestEntityListener::$inserted) {
            $consumer->consume();
        }

        $this->assertEquals($entity, MyTestEntityListener::$inserted[0]);

        $updated = clone $entity;
        $updated->value = 'Bar';
        $updated->update();

        while (!MyTestEntityListener::$updated) {
            $consumer->consume();
        }

        $this->assertEquals([$entity, $updated], MyTestEntityListener::$updated[0]);

        $updated->delete();


        while (!MyTestEntityListener::$deleted) {
            $consumer->consume();
        }

        $this->assertEquals($updated, MyTestEntityListener::$deleted[0]);

        $consumer->stop();
    }

    /**
     *
     */
    public function test_forConnection_functional_with_configured_logFile()
    {
        /** @var EntityEventsConsumer $consumer */
        $consumer = $this->container->get(ConsumersFactory::class)->forConnection('other');
        $consumer->start();

        $entity = new OtherEntity(5);
        $entity->insert();

        while (!OtherEntityListener::$inserted) {
            $consumer->consume();
        }

        $this->assertEquals($entity, OtherEntityListener::$inserted[0]);
        $consumer->stop();

        $this->assertFileExists(__DIR__.'/../../var/events');

        /** @var EntityEventsConsumer $consumer */
        $consumer = $this->container->get(ConsumersFactory::class)->forConnection('other');

        $entity->delete();

        while (!OtherEntityListener::$deleted) {
            $consumer->consume();
        }

        $this->assertEquals($entity, OtherEntityListener::$deleted[0]);

        $consumer->stop();
        unlink(__DIR__.'/../../var/events');
    }

    /**
     *
     */
    public function test_forConnection_should_filter_listeners()
    {
        $factory = new ConsumersFactory($this->container->get(ServiceLocator::class));
        $factory->register($myListener = new MyTestEntityListener());
        $factory->register($otherListener = new OtherEntityListener());

        $insertListener = new \ReflectionProperty(EntityEventsListener::class, 'insertListeners');
        $insertListener->setAccessible(true);

        $consumer1 = $factory->forConnection('test');
        $this->assertSame($myListener, $insertListener->getValue($consumer1->forEntity(MyTestEntity::class))[0][0]);
        $this->assertEmpty($insertListener->getValue($consumer1->forEntity(OtherEntity::class)));

        $consumer2 = $factory->forConnection('other');
        $this->assertEmpty($insertListener->getValue($consumer2->forEntity(MyTestEntity::class)));
        $this->assertSame($otherListener, $insertListener->getValue($consumer2->forEntity(OtherEntity::class))[0][0]);
    }
}
