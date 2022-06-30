<?php

namespace Bdf\PrimeEvents;

use Bdf\Prime\Mapper\Mapper;
use Bdf\Prime\Platform\PlatformInterface;
use Bdf\Prime\Repository\RepositoryInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Listener for database entities writes
 *
 * @template E as object
 */
final class EntityEventsListener
{
    /**
     * @var RepositoryInterface<E>
     */
    private $repository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Mapper<E>
     */
    private $mapper;

    /**
     * @var PlatformInterface
     */
    private $platform;

    /**
     * @var array<callable(E):void>
     */
    private $insertListeners = [];

    /**
     * @var array<callable(E, E):void>
     */
    private $updateListeners = [];

    /**
     * @var array<callable(E):void>
     */
    private $deleteListeners = [];

    /**
     * EntityEventsListener constructor.
     *
     * @param RepositoryInterface<E> $repository
     * @param LoggerInterface|null $logger
     *
     * @throws \Bdf\Prime\Exception\PrimeException
     */
    public function __construct(RepositoryInterface $repository, ?LoggerInterface $logger = null)
    {
        $this->repository = $repository;
        $this->mapper = $repository->mapper();
        $this->platform = $repository->connection()->platform();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @internal
     */
    public function onWrite(array $value): void
    {
        $this->logger->info('[MySQL Event] write on ' . $this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify($this->insertListeners, $this->entity($value));
    }

    /**
     * @param array{before: array, after: array} $value
     * @internal
     */
    public function onUpdate(array $value): void
    {
        $this->logger->info('[MySQL Event] update on ' . $this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify($this->updateListeners, $this->entity($value['before']), $this->entity($value['after']));
    }

    /**
     * @internal
     */
    public function onDelete(array $value): void
    {
        $this->logger->info('[MySQL Event] delete on ' . $this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify($this->deleteListeners, $this->entity($value));
    }

    /**
     * Register post insert event
     *
     * @param callable(E):void $listener
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
     * @param callable(E, E):void $listener
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
     * @param callable(E):void $listener
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
    private function entity(array $value)
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
                } elseif (is_array($listener)) {
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
