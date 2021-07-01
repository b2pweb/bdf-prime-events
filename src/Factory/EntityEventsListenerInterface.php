<?php

namespace Bdf\PrimeEvents\Factory;

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
     */
    public function onInsert($entity): void;

    /**
     * The entity has been updated
     *
     * @param E $oldEntity The previous value of the entity
     * @param E $newEntity The new value of the entity
     */
    public function onUpdate($oldEntity, $newEntity): void;

    /**
     * The entity has been delete
     *
     * @param E $entity The delete entity
     */
    public function onDelete($entity): void;
}
