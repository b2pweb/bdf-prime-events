<?php

namespace Bdf\PrimeEvents;

use Bdf\Event\EventNotifier;
use Bdf\Prime\Events;
use Bdf\Prime\Mapper\Mapper;
use Bdf\Prime\Platform\PlatformInterface;
use Bdf\Prime\Repository\RepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Listener for database entities writes
 *
 * @todo set entity template when feature psalm is merged
 */
final class EntityEventsListener
{
    use EventNotifier;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var PlatformInterface
     */
    private $platform;

    /**
     * EntityEventsListener constructor.
     *
     * @param RepositoryInterface $repository
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
        $this->logger->info('[MySQL Event] write on '.$this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify(Events::POST_INSERT, [$this->entity($value)]);
    }

    /**
     * @param array{before: array, after: array} $value
     * @internal
     */
    public function onUpdate(array $value): void
    {
        $this->logger->info('[MySQL Event] update on '.$this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify(Events::POST_UPDATE, [$this->entity($value['before']), $this->entity($value['after'])]);
    }

    /**
     * @internal
     */
    public function onDelete(array $value): void
    {
        $this->logger->info('[MySQL Event] delete on '.$this->mapper->getEntityClass(), ['value' => $value]);
        $this->notify(Events::POST_DELETE, [$this->entity($value)]);
    }

    /**
     * Register post insert event
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function inserted(callable $listener): EntityEventsListener
    {
        $this->listen(Events::POST_INSERT, $listener);

        return $this;
    }

    /**
     * Register post update event
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function updated(callable $listener): EntityEventsListener
    {
        $this->listen(Events::POST_UPDATE, $listener);

        return $this;
    }

    /**
     * Register post delete event
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function deleted(callable $listener): EntityEventsListener
    {
        $this->listen(Events::POST_DELETE, $listener);

        return $this;
    }

    /**
     * Convert event data to entity
     *
     * @param array $value
     * @return object
     */
    private function entity(array $value)
    {
        return $this->mapper->prepareFromRepository($value, $this->platform);
    }
}
