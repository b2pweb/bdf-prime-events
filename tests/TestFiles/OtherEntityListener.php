<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\PrimeEvents\Factory\EntityEventsListenerInterface;

class OtherEntityListener implements EntityEventsListenerInterface
{
    public static $inserted = [];
    public static $deleted = [];
    public static $updated = [];

    public function __construct()
    {
        self::$inserted = self::$deleted = self::$updated = [];
    }

    public function entityClass(): string
    {
        return OtherEntity::class;
    }

    public function onInsert($entity): void
    {
        self::$inserted[] = $entity;
    }

    public function onUpdate($oldEntity, $newEntity): void
    {
        self::$updated[] = [$oldEntity, $newEntity];
    }

    public function onDelete($entity): void
    {
        self::$deleted[] = $entity;
    }
}
