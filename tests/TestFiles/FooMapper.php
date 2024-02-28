<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\Prime\Mapper\Mapper;

class FooMapper extends Mapper
{
    /**
     * @return array|null
     */
    public function schema(): array
    {
        return [
            'connection' => 'test',
            'table' => 'foo'
        ];
    }

    public function buildFields($builder): void
    {
        $builder
            ->integer('id')->autoincrement()
            ->string('foo')
        ;
    }
}
