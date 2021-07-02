# Prime Events
[![build](https://github.com/b2pweb/bdf-prime-events/actions/workflows/php.yml/badge.svg)](https://github.com/b2pweb/bdf-prime-events/actions/workflows/php.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/b2pweb/bdf-prime-events/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/b2pweb/bdf-prime-events/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/b2pweb/bdf-prime-events/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/b2pweb/bdf-prime-events/?branch=master)
[![Packagist Version](https://img.shields.io/packagist/v/b2pweb/bdf-prime-events.svg)](https://packagist.org/packages/b2pweb/bdf-prime-events)
[![Total Downloads](https://img.shields.io/packagist/dt/b2pweb/bdf-prime-events.svg)](https://packagist.org/packages/b2pweb/bdf-prime-events)
[![Type Coverage](https://shepherd.dev/github/b2pweb/bdf-prime-events/coverage.svg)](https://shepherd.dev/github/b2pweb/bdf-prime-events)

Prime extension for listen MySQL replication events on Prime entities to track insert, update and delete operations.

## Installation

Install with composer :

```bash
composer require b2pweb/bdf-prime-events
```

### Configuration on Symfony

Register into `config/bundles.php` :

```php
<?php

return [
    // ...
    Bdf\PrimeEvents\Bundle\PrimeEventsBundle::class => ['all' => true],
    Bdf\PrimeBundle\PrimeBundle::class => ['all' => true],
];
```

Configure indexes into `config/packages/prime_events.yaml` :

```yaml
prime_events:
  # Configure replication connection parameter here, by connection name
  my_connection:
    user: other_user # Define a custom username/password which as REPLICATION CLIENT and REPLICATION SLAVE permissions
    password: other_pass
    logPositionFile: '%kernel.project_dir%/var/events' # The file for store the last consumed event, to allow restart consumer without loose events 

```

Enable autoconfigure on application to let Symfony container configure the listeners :

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true

  App\Entity\Listener\:
    resource: './src/Entity/Listener'

```

### Configure MySQL

See [krowinski/php-mysql-replication](https://github.com/krowinski/php-mysql-replication#mysql-server-settings) for enable replication protocol on the MySQL server.

## Usage

Prime entities are use for events, see [Create your mapper](https://github.com/b2pweb/bdf-prime#create-your-mapper) to define an entity.

### Simple usage / without Symfony

Simply create an `EntityEventsConsumer`, define listeners, and run the consumer :

```php
use Bdf\PrimeEvents\EntityEventsConsumer;
use MySQLReplication\Config\ConfigBuilder;

$consumer = new EntityEventsConsumer(
    $prime, // The ServiceLocator instance
    __DIR__.'/mysql_last_event.log', // File for store the last consumed event, to allow restart without loosing events
    function (ConfigBuilder $config) {
        $config
            // Define custom connection configuration
            // Note: by default, the connection user and password is used
            // So it's not required to redefine it if the user has the replication permissions
            ->withUser('replication_user')
            ->withPassword('replication_pass')
            // Define the slave id. define this value is required if you want to run multiple
            // consumers on the same database
            ->withSlaveId(123) 
        ;
    }
);

// Configure listener for MyEntity
$consumer->forEntity(MyEntity::class)
    ->inserted(function (MyEntity $entity) { /* $entity has been inserted */})
    ->updated(function (MyEntity $before, MyEntity $now) { /* The entity has been updated. $before is its value before the update, and $now the current value */ })
    ->deleted(function (MyEntity $entity) { /* $entity has been deleted */})
;

// Other entities may be configure...

// Consume all events
// Note: consume() will only consume 1 event
while ($running) {
    $consumer->consume();
}

// Stop the consumer and save the last consumed events
$consumer->stop();
```

### Usage with Symfony

Symfony will autoconfigure the listeners if there implements `EntityEventsListenerInterface` :

```php
use Bdf\PrimeEvents\Factory\EntityEventsListenerInterface;

/**
 * @implements EntityEventsListenerInterface<MyEntity>
 */
class MyEntityListeners implements EntityEventsListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function entityClass() : string
    {
        return MyEntity::class;
    }

    /**
     * {@inheritdoc} 
     * @param MyEntity $entity
     */
    public function onInsert($entity) : void
    {
        // $entity has been inserted
    }
    
    /**
     * {@inheritdoc} 
     * @param MyEntity $oldEntity
     * @param MyEntity $newEntity
     */
    public function onUpdate($oldEntity, $newEntity) : void
    {
        // The entity has been updated.
        // $before is its value before the update, and $now the current value
    }
    
    /**
     * {@inheritdoc} 
     * @param MyEntity $entity
     */
    public function onDelete($entity) : void
    {
        // $entity has been deleted
    }  
}
```

To consume events, simply launch `prime:events:consume` command :

```
bin/console prime:events:consume my_connection --limit 10000 --memory 500m
```
