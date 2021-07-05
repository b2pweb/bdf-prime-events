<?php

namespace Tests\PrimeEvents\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\PrimeEvents\PrimeEventsTestKernel;
use Tests\PrimeEvents\TestFiles\MyTestEntity;
use Tests\PrimeEvents\TestFiles\MyTestEntityListener;

/**
 * Class ConsumePrimeEventsTests
 * @package Tests\PrimeEvents\Console
 */
class ConsumePrimeEventsTest extends TestCase
{
    /**
     * @var Application
     */
    private $console;
    /**
     * @var Command
     */
    private $command;

    /**
     * @var CommandTester
     */
    private $tester;

    /**
     * @var int
     */
    private $pid;

    protected function setUp(): void
    {
        $kernel = new PrimeEventsTestKernel('dev', false);
        $kernel->boot();

        $this->console = new Application($kernel);
        $this->command = $this->console->get('prime:events:consume');
        $this->tester = new CommandTester($this->command);

        MyTestEntity::repository()->schema()->migrate();
    }

    protected function tearDown(): void
    {
        if ($this->pid !== null) {
            @posix_kill($this->pid, SIGKILL);
        }

        MyTestEntityListener::$inserted = MyTestEntityListener::$deleted = MyTestEntityListener::$updated = [];
        MyTestEntity::repository()->schema()->drop();
    }

    /**
     *
     */
    public function test_execute_simple()
    {
        $this->runInBackground(100, function () {
            (new MyTestEntity(['id' => 1, 'value' => 'Foo']))->insert();
        });

        $this->tester->execute(['connection' => 'test', '--limit' => 5]);

        $this->assertEquals(new MyTestEntity(['id' => 1, 'value' => 'Foo']), MyTestEntityListener::$inserted[0]);
    }

    /**
     *
     */
    public function test_execute_with_limit()
    {
        $this->runInBackground(100, function () {
            for ($i = 0; $i < 100; ++$i) {
                (new MyTestEntity(['value' => 'Foo']))->insert();
            }
        });

        $this->tester->execute(['connection' => 'test', '--limit' => 50]);
        sleep(1);

        $count = count(MyTestEntityListener::$inserted);
        $this->assertGreaterThan(5, $count);
        $this->assertLessThan(50, $count);
    }

    /**
     *
     */
    public function test_execute_with_memory()
    {
        $this->runInBackground(100, function () {
            for ($i = 0; $i < 5000; ++$i) {
                (new MyTestEntity(['value' => bin2hex(random_bytes(127))]))->insert();
            }
        });

        $startMemory = memory_get_usage();

        $this->tester->execute(['connection' => 'test', '--memory' => $startMemory + 1024*1024]); // +1Mo
        sleep(5);

        $count = count(MyTestEntityListener::$inserted);
        $this->assertGreaterThan(1000, $count);
        $this->assertLessThan(4000, $count);
    }

    /**
     * @param int $after Delay before execute the action in ms
     * @param callable $action Action to perform in background
     */
    private function runInBackground(int $after, callable $action): void
    {
        // Parent process
        if (($this->pid = pcntl_fork()) !== 0) {
            return;
        }

        usleep($after * 1000);

        try {
            $action();
        } catch (\Throwable $e) {}
        exit;
    }
}
