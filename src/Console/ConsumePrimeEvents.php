<?php

namespace Bdf\PrimeEvents\Console;

use Bdf\PrimeEvents\EntityEventsConsumer;
use Bdf\PrimeEvents\Factory\ConsumersFactory;
use Bdf\Util\Console\ByteConverterExtension;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Throwable;
use function memory_get_usage;
use function pcntl_async_signals;
use function pcntl_signal;

/**
 * Command for consume MySQL replication events
 */
class ConsumePrimeEvents extends Command
{
    use ByteConverterExtension;

    protected static $defaultName = 'prime:events:consume';

    /**
     * @var ConsumersFactory
     */
    private $factory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $running = true;

    /**
     * @var int|null
     */
    private $maxMemory = null;

    /**
     * @var int|null
     */
    private $limit = null;


    /**
     * PrimeEventsConsumer constructor.
     *
     * @param ConsumersFactory $factory
     * @param LoggerInterface|null $logger
     */
    public function __construct(ConsumersFactory $factory, ?LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->factory = $factory;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Consume MySQL replication events and execute listeners')
            ->addOption('limit', 'c', InputOption::VALUE_OPTIONAL, 'Maximum read events count')
            ->addOption('memory', 'm', InputOption::VALUE_OPTIONAL, 'Memory limit')
            ->addArgument('connection', InputArgument::REQUIRED, 'The connection name to listen')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->parseOptions($input);
        $consumer = $this->factory->forConnection($input->getArgument('connection'));
        $this->runConsumer($consumer);

        return 0;
    }

    private function parseOptions(InputInterface $input): void
    {
        if ($input->getOption('limit') !== null) {
            $this->limit = (int) $input->getOption('limit');
        }

        if ($input->getOption('memory') !== null) {
            $this->maxMemory = $this->convertToBytes((string) $input->getOption('memory'));
        }
    }

    private function runConsumer(EntityEventsConsumer $consumer): void
    {
        $stop = function () use ($consumer) {
            $this->running = false;
        };

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);

        while ($this->isRunning()) {
            try {
                $consumer->consume();
            } catch (Throwable $e) {
                $this->logger->error('[MySQL Event] Uncaught exception during consume : '.$e);
            }
        }

        $consumer->stop();
    }

    private function isRunning(): bool
    {
        if ($this->limit !== null && $this->limit-- <= 0) {
            return false;
        }

        if ($this->maxMemory !== null && memory_get_usage() > $this->maxMemory) {
            return false;
        }

        return $this->running;
    }
}
