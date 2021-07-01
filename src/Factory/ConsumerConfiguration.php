<?php

namespace Bdf\PrimeEvents\Factory;

use MySQLReplication\Config\ConfigBuilder;

/**
 * Store configuration for events consumers
 */
final class ConsumerConfiguration
{
    /**
     * @var array
     */
    private $config;

    /**
     * ConsumerConfiguration constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get the file which stores the last consumed event
     *
     * @return string|null The filename or null if not provided
     */
    public function logPositionFile(): ?string
    {
        return $this->config['logPositionFile'] ?? null;
    }

    /**
     * Configure the MySQL replication config builder
     *
     * @param ConfigBuilder $builder
     */
    public function configure(ConfigBuilder $builder)
    {
        foreach ($this->config as $item => $value) {
            $method = 'with' . $item;

            if (method_exists($builder, $method)) {
                $builder->$method($value);
            }
        }
    }
}
