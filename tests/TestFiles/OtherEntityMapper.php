<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\Prime\Mapper\Mapper;

class OtherEntityMapper extends Mapper
{
    public function schema(): array
    {
        return [
            'connection' => 'other',
            'table' => 'other',
        ];
    }

    public function buildFields($builder): void
    {
        $builder->integer('id')->autoincrement();
    }
}
