<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\PrimeEvents\Factory\EntityEventsListenerInterface;
use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\WriteRowsDTO;

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

    public function onInsert($entity, ?WriteRowsDTO $event = null): void
    {
        self::$inserted[] = $entity;
    }

    public function onUpdate($oldEntity, $newEntity, ?UpdateRowsDTO $event = null): void
    {
        self::$updated[] = [$oldEntity, $newEntity];
    }

    public function onDelete($entity, ?DeleteRowsDTO $event = null): void
    {
        self::$deleted[] = $entity;
    }
}
