<?php

namespace Bdf\PrimeEvents;

use Bdf\Prime\Mapper\Mapper;
use Bdf\Prime\Platform\PlatformInterface;
use Bdf\Prime\Repository\RepositoryInterface;
use Exception;
use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\WriteRowsDTO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function is_object;

/**
 * Listener for database entities writes
 *
 * @template E as object
 */
final class EntityEventsListener
{
    /**
     * @var Mapper<E>
     */
    private readonly Mapper $mapper;
    private readonly PlatformInterface $platform;

    /**
     * @var array<callable(E, WriteRowsDTO=):void>
     */
    private array $insertListeners = [];

    /**
     * @var array<callable(E, E, UpdateRowsDTO=):void>
     */
    private array $updateListeners = [];

    /**
     * @var array<callable(E, DeleteRowsDTO=):void>
     */
    private array $deleteListeners = [];

    /**
     * @throws \Bdf\Prime\Exception\PrimeException
     */
    public function __construct(
        /**
         * @var RepositoryInterface<E>
         */
        private readonly RepositoryInterface $repository,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        $this->mapper = $repository->mapper();
        $this->platform = $repository->connection()->platform();
    }

    /**
     * @internal
     */
    public function onWrite(array $value, WriteRowsDTO $event): void
    {
        $this->logger->info('[MySQL Event] write on ' . $this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify($this->insertListeners, $this->entity($value), $event);
    }

    /**
     * @param array{before: array, after: array} $value
     * @internal
     */
    public function onUpdate(array $value, UpdateRowsDTO $event): void
    {
        $this->logger->info('[MySQL Event] update on ' . $this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify($this->updateListeners, $this->entity($value['before']), $this->entity($value['after']), $event);
    }

    /**
     * @internal
     */
    public function onDelete(array $value, DeleteRowsDTO $event): void
    {
        $this->logger->info('[MySQL Event] delete on ' . $this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify($this->deleteListeners, $this->entity($value), $event);
    }

    /**
     * Register post insert event
     *
     * @param callable(E, WriteRowsDTO=):void $listener
     *
     * @return $this
     */
    public function inserted(callable $listener): EntityEventsListener
    {
        $this->insertListeners[] = $listener;

        return $this;
    }

    /**
     * Register post update event
     *
     * @param callable(E, E, UpdateRowsDTO=):void $listener
     *
     * @return $this
     */
    public function updated(callable $listener): EntityEventsListener
    {
        $this->updateListeners[] = $listener;

        return $this;
    }

    /**
     * Register post delete event
     *
     * @param callable(E, DeleteRowsDTO=):void $listener
     *
     * @return $this
     */
    public function deleted(callable $listener): EntityEventsListener
    {
        $this->deleteListeners[] = $listener;

        return $this;
    }

    /**
     * Convert event data to entity
     *
     * @param array $value
     * @return E
     */
    private function entity(array $value): object
    {
        return $this->mapper->prepareFromRepository($value, $this->platform);
    }

    /**
     * @param array<callable> $listeners
     * @param mixed ...$args
     * @return void
     */
    private function notify(array $listeners, ...$args): void
    {
        foreach ($listeners as $listener) {
            try {
                $listener(...$args);
            } catch (Exception $e) {
                if (is_object($listener)) {
                    $listenerName = get_class($listener);
                } elseif (is_array($listener) && is_object($listener[0])) {
                    $listenerName = get_class($listener[0]);
                } elseif (is_string($listener)) {
                    $listenerName = $listener;
                } else {
                    $listenerName = 'anonymous';
                }

                $this->logger->error(
                    sprintf(
                        'Error during the execution of listener "%s" for entity "%s" : %s',
                        $listenerName,
                        $this->repository->entityName(),
                        $e->getMessage()
                    ),
                    ['exception' => $e]
                );
            }
        }
    }
}
