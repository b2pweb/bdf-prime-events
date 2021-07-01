<?php

namespace Tests\PrimeEvents\Factory;

use Bdf\PrimeEvents\Factory\ConsumerConfiguration;
use MySQLReplication\Config\ConfigBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Class ConsumerConfigurationTest
 * @package Tests\PrimeEvents\Factory
 */
class ConsumerConfigurationTest extends TestCase
{
    /**
     *
     */
    public function test_logPositionFile()
    {
        $config = new ConsumerConfiguration([]);
        $this->assertNull($config->logPositionFile());

        $config = new ConsumerConfiguration(['logPositionFile' => __DIR__.'/events']);
        $this->assertSame(__DIR__.'/events', $config->logPositionFile());
    }

    /**
     *
     */
    public function test_configure()
    {
        $config = new ConsumerConfiguration(['user' => 'my_user', 'password' => 'my_password']);
        $config->configure($builder = new ConfigBuilder());

        $built = $builder->build();

        $this->assertEquals('my_user', $built->getUser());
        $this->assertEquals('my_password', $built->getPassword());
    }
}
