<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\Prime\Mapper\Mapper;

class MyTestEntityMapper extends Mapper
{
    public function schema()
    {
        return [
            'connection' => 'test',
            'table' => 'my_entity'
        ];
    }

    public function buildFields($builder)
    {
        $builder
            ->integer('id')->autoincrement()
            ->string('value')
        ;
    }
}
