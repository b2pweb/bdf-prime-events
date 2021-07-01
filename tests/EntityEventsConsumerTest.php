<?php

namespace Tests\PrimeEvents;

use Bdf\Prime\Entity\Model;
use Bdf\Prime\Mapper\Mapper;
use Bdf\Prime\ServiceLocator;
use Bdf\PrimeEvents\EntityEventsConsumer;
use MySQLReplication\BinLog\BinLogCurrent;
use PHPUnit\Framework\TestCase;

/**
 * Class EntityEventsConsumerTest
 */
class EntityEventsConsumerTest extends TestCase
{
    /**
     * @var ServiceLocator|object|null
     */
    private $prime;

    /**
     *
     */
    protected function setUp(): void
    {
        $kernel = new PrimeEventsTestKernel('dev', false);
        $kernel->boot();

        $this->prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->prime->repository(Foo::class)->schema()->migrate();
    }

    /**
     *
     */
    protected function tearDown(): void
    {
        Foo::repository()->schema()->drop();
    }

    /**
     *
     */
    public function test_consume()
    {
        $inserted = [];
        $deleted = [];
        $updated = [];

        $consumer = new EntityEventsConsumer($this->prime);
        $consumer
            ->forEntity(Foo::class)
            ->inserted(function ($entity) use(&$inserted) {
                $inserted[] = $entity;
            })
            ->deleted(function ($entity) use(&$deleted) {
                $deleted[] = $entity;
            })
            ->updated(function ($before, $after) use(&$updated) {
                $updated[] = [$before, $after];
            })
        ;

        $consumer->start();

        $entity = new Foo(['foo' => 'bar']);
        $entity->save();

        while (empty($inserted)) {
            $consumer->consume();
        }

        $this->assertEquals(new Foo(['id' => 1, 'foo' => 'bar']), $inserted[0]);

        $entity->foo = 'oof';
        $entity->update();

        while (empty($updated)) {
            $consumer->consume();
        }

        $this->assertEquals([new Foo(['id' => 1, 'foo' => 'bar']), new Foo(['id' => 1, 'foo' => 'oof'])], $updated[0]);

        $entity->delete();
        while (empty($deleted)) {
            $consumer->consume();
        }

        $this->assertEquals(new Foo(['id' => 1, 'foo' => 'oof']), $deleted[0]);
        $consumer->stop();
    }

    /**
     *
     */
    public function test_multiple_insert()
    {
        $inserted = [];

        $consumer = new EntityEventsConsumer($this->prime);
        $consumer
            ->forEntity(Foo::class)
            ->inserted(function ($entity) use(&$inserted) {
                $inserted[] = $entity;
            })
        ;

        $consumer->start();

        $entities = [
            new Foo(['foo' => 'bar1']),
            new Foo(['foo' => 'bar2']),
            new Foo(['foo' => 'bar3']),
        ];

        foreach ($entities as $entity) {
            $entity->save();
        }

        while (count($inserted) !== 3) {
            $consumer->consume();
        }

        $this->assertEquals($entities, $inserted);
        $consumer->stop();
    }

    /**
     *
     */
    public function test_multiple_update()
    {
        $updated = [];

        $consumer = new EntityEventsConsumer($this->prime);
        $consumer
            ->forEntity(Foo::class)
            ->updated(function ($before, $after) use(&$updated) {
                $updated[] = [$before, $after];
            })
        ;

        $consumer->start();

        $entity = new Foo(['foo' => 'bar']);
        $entity->insert();

        $entity->foo = 'bar1';
        $entity->update();

        $entity->foo = 'bar2';
        $entity->update();


        $entity->foo = 'bar3';
        $entity->update();

        while (count($updated) !== 3) {
            $consumer->consume();
        }

        $this->assertEquals([
            [new Foo(['id' => 1, 'foo' => 'bar']), new Foo(['id' => 1, 'foo' => 'bar1'])],
            [new Foo(['id' => 1, 'foo' => 'bar1']), new Foo(['id' => 1, 'foo' => 'bar2'])],
            [new Foo(['id' => 1, 'foo' => 'bar2']), new Foo(['id' => 1, 'foo' => 'bar3'])],
        ], $updated);
        $consumer->stop();
    }

    /**
     *
     */
    public function test_consume_should_restart_from_last_event()
    {
        $inserted = [];

        $file = tempnam('/tmp', 'consumer_pos');
        $consumer = new EntityEventsConsumer($this->prime, $file);
        $consumer
            ->forEntity(Foo::class)
            ->inserted(function ($entity) use(&$inserted) {
                $inserted[] = $entity;
            })
        ;

        $consumer->start();
        $e1 = new Foo(['foo' => 'bar1']);
        $e1->save();

        while (count($inserted) !== 1) {
            $consumer->consume();
        }

        $consumer->stop();
        $this->assertInstanceOf(BinLogCurrent::class, unserialize(file_get_contents($file)));

        $this->assertEquals([$e1], $inserted);

        $e2 = new Foo(['foo' => 'bar2']);
        $e2->save();

        $consumer = new EntityEventsConsumer($this->prime, $file);
        $consumer
            ->forEntity(Foo::class)
            ->inserted(function ($entity) use(&$inserted) {
                $inserted[] = $entity;
            })
        ;
        $consumer->start();

        while (count($inserted) !== 2) {
            $consumer->consume();
        }

        $this->assertEquals([$e1, $e2], $inserted);
        $consumer->stop();
    }
}

class Foo extends Model
{
    public $id;
    public $foo;

    public function __construct(array $data = [])
    {
        $this->import($data);
    }
}

class FooMapper extends Mapper
{
    /**
     * @return array|null
     */
    public function schema()
    {
        return [
            'connection' => 'test',
            'table' => 'foo'
        ];
    }

    public function buildFields($builder)
    {
        $builder
            ->integer('id')->autoincrement()
            ->string('foo')
        ;
    }
}
