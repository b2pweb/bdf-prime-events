<?php

namespace Bdf\PrimeEvents\Factory;

use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\WriteRowsDTO;

/**
 * Base type for listen MySQL events on an entity
 * On Symfony, this interface will be autoconfigured and registered on ConsumersFactory
 *
 * @template E as object
 */
interface EntityEventsListenerInterface
{
    /**
     * The handled entity class name
     *
     * @return class-string<E>
     */
    public function entityClass(): string;

    /**
     * The entity has been inserted
     *
     * @param E $entity
     * @param WriteRowsDTO|null $event
     */
    public function onInsert($entity/*, ?WriteRowsDTO $event = null*/): void;

    /**
     * The entity has been updated
     *
     * @param E $oldEntity The previous value of the entity
     * @param E $newEntity The new value of the entity
     * @param UpdateRowsDTO|null $event
     */
    public function onUpdate($oldEntity, $newEntity/*, ?UpdateRowsDTO $event = null*/): void;

    /**
     * The entity has been deleted
     *
     * @param E $entity The deleted entity
     * @param DeleteRowsDTO|null $event
     */
    public function onDelete($entity/*, ?DeleteRowsDTO $event = null*/): void;
}
