<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\Prime\Entity\Model;

class MyTestEntity extends Model
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $value;

    public function __construct(array $data = [])
    {
        $this->import($data);
    }
}
