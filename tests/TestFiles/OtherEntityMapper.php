<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\Prime\Mapper\Mapper;

class OtherEntityMapper extends Mapper
{
    public function schema()
    {
        return [
            'connection' => 'other',
            'table' => 'other',
        ];
    }

    public function buildFields($builder)
    {
        $builder->integer('id')->autoincrement();
    }
}
