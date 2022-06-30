<?php

namespace Bdf\PrimeEvents;

use Bdf\Prime\ServiceLocator;
use Bdf\PrimeEvents\Factory\ConsumersFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use InvalidArgumentException;
use MySQLReplication\BinLog\BinLogCurrent;
use MySQLReplication\Config\Config;
use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\WriteRowsDTO;
use MySQLReplication\Event\EventSubscribers;
use MySQLReplication\MySQLReplicationFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Consume MySQL replication events for watch entities writes
 *
 * Usage:
 * <code>
 * $consumer = new EntityEventsConsumer($prime, __DIR__ . '/var/events', function (ConfigBuilder $config) {
 *     $config->withUser('replication_user');
 *     $config->withPassword('replication_password');
 * });
 *
 * $consumer->forEntity(MyEntity::class)
 *     ->inserted(function (MyEntity $entity) { ... })
 *     ->deleted(function (MyEntity $entity) { ... })
 *     ->updated(function (MyEntity $before, MyEntity $now) { ... })
 * ;
 *
 * $consumer->start();
 *
 * while ($running) {
 *     $consumer->consume();
 * }
 *
 * $consumer->stop();
 * </code>
 *
 * @see ConsumersFactory For instantiate and confugure the consummer
 */
final class EntityEventsConsumer extends EventSubscribers
{
    /**
     * @var ServiceLocator
     */
    private $prime;

    /**
     * @var callable|null
     */
    private $configurator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array<class-string, EntityEventsListener>
     * @psalm-var class-string-map<E, EntityEventsListener<E>>
     */
    private $entityListenersByEntityClass = [];

    /**
     * @var array<string, EntityEventsListener>
     */
    private $entityListenersByTable = [];

    /**
     * @var MySQLReplicationFactory|null
     */
    private $binLogStream;

    /**
     * @var string|null
     */
    private $logPositionFile;

    /**
     * @var BinLogCurrent|null
     */
    private $binLogCurrent;


    /**
     * EntityEventsListener constructor.
     *
     * @param ServiceLocator $prime Prime
     * @param string|null $logPositionFile File which store the last event log position
     * @param callable|null $configurator Configurator callback
     */
    public function __construct(ServiceLocator $prime, ?string $logPositionFile = null, ?callable $configurator = null, ?LoggerInterface $logger = null)
    {
        $this->prime = $prime;
        $this->configurator = $configurator;
        $this->logPositionFile = $logPositionFile;
        $this->logger = $logger ?? new NullLogger();

        $this->loadLastPosition();
    }

    /**
     * Listen write events for the given entity
     *
     * Usage:
     * <code>
     * $consumer->forEntity(MyEntity::class)
     *     ->inserted(function (MyEntity $entity) { ... })
     *     ->deleted(function (MyEntity $entity) { ... })
     *     ->updated(function (MyEntity $before, MyEntity $now) { ... })
     * ;
     * </code>
     *
     * @param class-string<E> $entityClass The entity class name
     *
     * @return EntityEventsListener<E> The listener instance
     * @template E as object
     */
    public function forEntity(string $entityClass): EntityEventsListener
    {
        if (isset($this->entityListenersByEntityClass[$entityClass])) {
            return $this->entityListenersByEntityClass[$entityClass];
        }

        $repository = $this->prime->repository($entityClass);
        $listener = new EntityEventsListener($repository, $this->logger);

        return $this->entityListenersByTable[$repository->metadata()->table()]
            = $this->entityListenersByEntityClass[$entityClass]
            = $listener
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function onDelete(DeleteRowsDTO $event): void
    {
        parent::onDelete($event);

        $listener = $this->entityListenersByTable[$event->getTableMap()->getTable()];

        /** @var array $value */
        foreach ($event->getValues() as $value) {
            $listener->onDelete($value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onWrite(WriteRowsDTO $event): void
    {
        parent::onWrite($event);

        $listener = $this->entityListenersByTable[$event->getTableMap()->getTable()];

        /** @var array $value */
        foreach ($event->getValues() as $value) {
            $listener->onWrite($value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onUpdate(UpdateRowsDTO $event): void
    {
        parent::onUpdate($event);

        $listener = $this->entityListenersByTable[$event->getTableMap()->getTable()];

        /** @var array{before: array, after: array} $value */
        foreach ($event->getValues() as $value) {
            $listener->onUpdate($value);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function allEvents(EventDTO $event): void
    {
        $this->logger->debug((string) $event);
        $this->binLogCurrent = $event->getEventInfo()->getBinLogCurrent();
    }

    /**
     * Configure the consumer
     *
     * @psalm-assert MySQLReplicationFactory $this->binLogStream
     */
    public function start(): void
    {
        if ($this->binLogStream !== null) {
            return;
        }

        $this->binLogStream = new MySQLReplicationFactory($this->config());
        $this->binLogStream->registerSubscriber($this);
    }

    /**
     * Consume one MySQL event
     * Note: This method will automatically starts the consumer
     *
     * @throws \Exception
     */
    public function consume(): void
    {
        $this->start();
        $this->binLogStream->consume();
    }

    /**
     * Unconfigure the consumer, and save the last consumed event
     *
     * @psalm-assert null $this->binLogStream
     */
    public function stop(): void
    {
        if ($this->binLogStream === null) {
            return;
        }

        $this->binLogStream = null;
        $this->saveLastPosition();
    }

    private function config(): Config
    {
        $databases = [];
        $tables = [];
        $username = null;
        $password = null;
        $host = null;

        foreach ($this->entityListenersByEntityClass as $entityClass => $_) {
            $repository = $this->prime->repository($entityClass);
            $connection = $repository->connection();

            if ($dbName = $connection->getDatabase()) {
                $databases[$dbName] = $dbName;
            }

            $tables[] = $repository->metadata()->table();

            if ($connection instanceof Connection) {
                if (!$connection->getDatabasePlatform() instanceof MySQLPlatform) {
                    throw new InvalidArgumentException(sprintf(
                        'The connection "%s" must be a MySQL connection',
                        $connection->getName()
                    ));
                }

                /** @psalm-suppress InternalMethod */
                $params = $connection->getParams();

                $username = $username ?? $params['user'] ?? null;
                $password = $password ?? $params['password'] ?? null;
                $host = $host ?? $params['host'] ?? null;
            }
        }

        $builder = (new ConfigBuilder())
            ->withSlaveId(100)
            ->withHeartbeatPeriod(3)
            ->withDatabasesOnly(array_values($databases))
            ->withTablesOnly($tables)
        ;

        if ($this->binLogCurrent !== null) {
            $builder
                ->withBinLogPosition($this->binLogCurrent->getBinLogPosition())
                ->withBinLogFileName($this->binLogCurrent->getBinFileName())
            ;
        }

        if ($username) {
            $builder->withUser($username);
        }

        if ($password) {
            $builder->withPassword($password);
        }

        if ($host) {
            $builder->withHost($host);
        }

        if ($this->configurator) {
            ($this->configurator)($builder);
        }

        return $builder->build();
    }

    /**
     * Load the last bin log current position
     */
    private function loadLastPosition(): void
    {
        if (!$this->logPositionFile || !is_file($this->logPositionFile)) {
            return;
        }

        $content = unserialize(
            file_get_contents($this->logPositionFile),
            ['allowed_classes' => [BinLogCurrent::class]]
        );

        if (!$content instanceof BinLogCurrent) {
            return;
        }

        $this->binLogCurrent = $content;
    }

    /**
     * Save the last bin log current position
     */
    private function saveLastPosition(): void
    {
        if (!$this->logPositionFile || !$this->binLogCurrent) {
            return;
        }

        if (!is_dir(dirname($this->logPositionFile))) {
            mkdir(dirname($this->logPositionFile), 0777, true);
        }

        file_put_contents($this->logPositionFile, serialize($this->binLogCurrent));
    }
}
