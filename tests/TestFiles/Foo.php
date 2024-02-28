<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\Prime\Entity\Model;

class Foo extends Model
{
    public $id;
    public $foo;

    public function __construct(array $data = [])
    {
        $this->import($data);
    }
}
