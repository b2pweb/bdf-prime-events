<?php

namespace Bdf\PrimeEvents\Factory;

use Bdf\Prime\ServiceLocator;
use Bdf\PrimeEvents\EntityEventsConsumer;
use InvalidArgumentException;
use LogicException;

/**
 * Create and configure @see EntityEventsConsumer
 *
 * Usage:
 * <code>
 * $factory = new ConsumersFactory(
 *     $prime,
 *     [
 *         'db' => new ConsumerConfiguration([
 *              'user' => 'replication_user',
 *              'password' => 'replication_password',
 *              'logPositionFile' => __DIR__ . '/var/db_events'
 *          ]),
 *     ],
 *     [new MyEntityListener()]
 * );
 *
 * $consumer = $factory->forConnection('db');
 *
 * $consumer->start();
 *
 * while ($running) {
 *     $consumer->consume();
 * }
 *
 * $consumer->stop();
 * </code>
 */
final class ConsumersFactory
{
    /**
     * @var ServiceLocator
     */
    private $prime;

    /**
     * The consumers configurations indexed by connection name
     *
     * @var array<string, ConsumerConfiguration>
     */
    private $config;

    /**
     * @var EntityEventsListenerInterface[]
     */
    private $listeners;

    /**
     * ConsumersFactory constructor.
     *
     * @param ServiceLocator $prime
     * @param array<string, ConsumerConfiguration> $config Consumers configurations indexed by connection name
     * @param EntityEventsListenerInterface[] $listeners
     */
    public function __construct(ServiceLocator $prime, array $config = [], array $listeners = [])
    {
        $this->prime = $prime;
        $this->config = $config;
        $this->listeners = $listeners;
    }

    /**
     * Get the MySQL replication events consumer for the requested connection
     * Note: It's strongly discouraged to create two consumers on the same connection
     *
     * Usage:
     * <code>
     * $consumer = $factory->forConnection('db');
     *
     * while ($runner) {
     *     $consumer->consume();
     * }
     *
     * $consumer->stop();
     * </code>
     *
     * @param string $connection The connection to listen
     *
     * @return EntityEventsConsumer
     * @throws InvalidArgumentException If there is no listeners associated to the connection
     */
    public function forConnection(string $connection): EntityEventsConsumer
    {
        $listeners = $this->filterListenersForConnection($connection);

        if (empty($listeners)) {
            throw new InvalidArgumentException('Cannot found any entity listeners for connection name "' . $connection . '"');
        }


        $config = $this->config[$connection] ?? new ConsumerConfiguration([]);
        $consumer = new EntityEventsConsumer(
            $this->prime,
            $config->logPositionFile(),
            [$config, 'configure']
        );

        foreach ($listeners as $listener) {
            $consumer
                ->forEntity($listener->entityClass())
                ->inserted([$listener, 'onInsert'])
                ->updated([$listener, 'onUpdate'])
                ->deleted([$listener, 'onDelete'])
            ;
        }

        return $consumer;
    }

    /**
     * Add a new consumer configuration for a connection
     *
     * @param string $connection The connection name
     * @param array|ConsumerConfiguration $config The configuration
     */
    public function configure(string $connection, $config): void
    {
        $this->config[$connection] = $config instanceof ConsumerConfiguration ? $config : new ConsumerConfiguration($config);
    }

    /**
     * Register a new entity events listener
     *
     * @param EntityEventsListenerInterface $listener
     */
    public function register(EntityEventsListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Get all entity listeners using the requested connection
     *
     * @param string $connection The connection name
     *
     * @return EntityEventsListenerInterface[]
     */
    private function filterListenersForConnection(string $connection): array
    {
        $listeners = [];

        foreach ($this->listeners as $listener) {
            $repository = $this->prime->repository($listener->entityClass());

            if (!$repository) {
                throw new LogicException(sprintf('The entity "%s" cannot be found for the listener "%s"', $listener->entityClass(), get_class($listener)));
            }

            if ($repository->metadata()->connection === $connection) {
                $listeners[] = $listener;
            }
        }

        return $listeners;
    }
}
