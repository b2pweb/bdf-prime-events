<?php

namespace Bdf\PrimeEvents\Factory;

use MySQLReplication\Config\ConfigBuilder;

/**
 * Store configuration for events consumers
 */
final readonly class ConsumerConfiguration
{
    public function __construct(
        /**
         * @var array<string, mixed>
         */
        private array $config,
    ) {}

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
    public function configure(ConfigBuilder $builder): void
    {
        foreach ($this->config as $item => $value) {
            $method = 'with' . $item;

            if (method_exists($builder, $method)) {
                $builder->$method($value);
            }
        }
    }
}
