<?php

namespace Tests\PrimeEvents;

require_once __DIR__ . '/TestLogger.php';

use Bdf\Prime\Entity\Model;
use Bdf\Prime\Mapper\Mapper;
use Bdf\Prime\ServiceLocator;
use Bdf\PrimeEvents\EntityEventsConsumer;
use MySQLReplication\BinLog\BinLogCurrent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;
use Tests\PrimeEvents\TestFiles\Foo;

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

        $logger = new TestLogger();

        $consumer = new EntityEventsConsumer($this->prime, null, null, $logger);
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
        $this->assertTrue($logger->hasRecordThatContains('[MySQL Event] write on '.Foo::class, LogLevel::INFO));

        $entity->foo = 'oof';
        $entity->update();

        while (empty($updated)) {
            $consumer->consume();
        }

        $this->assertTrue($logger->hasRecordThatContains('[MySQL Event] update on '.Foo::class, LogLevel::INFO));
        $this->assertEquals([new Foo(['id' => 1, 'foo' => 'bar']), new Foo(['id' => 1, 'foo' => 'oof'])], $updated[0]);

        $entity->delete();
        while (empty($deleted)) {
            $consumer->consume();
        }

        $this->assertTrue($logger->hasRecordThatContains('[MySQL Event] delete on '.Foo::class, LogLevel::INFO));
        $this->assertEquals(new Foo(['id' => 1, 'foo' => 'oof']), $deleted[0]);
        $consumer->stop();
    }

    /**
     *
     */
    public function test_consume_with_invalid_binlog_position_should_retry()
    {
        $inserted = [];
        $deleted = [];
        $updated = [];

        $logger = new TestLogger();

        $logPosFile = tempnam(sys_get_temp_dir(), 'events_log_');
        $logPos = new BinLogCurrent();
        $logPos->setBinFileName('fakefile');
        $logPos->setBinLogPosition(404);

        file_put_contents($logPosFile, serialize($logPos));

        $consumer = new EntityEventsConsumer($this->prime, $logPosFile, null, $logger);
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
        $this->assertTrue($logger->hasRecordThatContains('[MySQL Event] Invalid binlog position : ', LogLevel::WARNING));

        $entity = new Foo(['foo' => 'bar']);
        $entity->save();

        while (empty($inserted)) {
            $consumer->consume();
        }

        $this->assertEquals(new Foo(['id' => 1, 'foo' => 'bar']), $inserted[0]);
        $this->assertTrue($logger->hasRecordThatContains('[MySQL Event] write on '.Foo::class, LogLevel::INFO));

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

    /**
     *
     */
    public function test_consume_with_exception_should_not_stop_other_listeners()
    {
        $inserted = [];

        $file = tempnam('/tmp', 'consumer_pos');
        $logger = $this->createMock(LoggerInterface::class);
        $consumer = new EntityEventsConsumer($this->prime, $file, null, $logger);
        $consumer
            ->forEntity(Foo::class)
            ->inserted(function ($entity) {
                throw new \Exception('my error');
            })
            ->inserted(function ($entity) use(&$inserted) {
                $inserted[] = $entity;
            })
        ;

        $logger->expects($this->once())->method('error')
            ->with('Error during the execution of listener "Closure" for entity "Tests\PrimeEvents\TestFiles\Foo" : my error', ['exception' => new \Exception('my error')])
        ;

        $consumer->start();
        $e1 = new Foo(['foo' => 'bar1']);
        $e1->save();

        while (count($inserted) !== 1) {
            $consumer->consume();
        }

        $consumer->stop();
    }
}
