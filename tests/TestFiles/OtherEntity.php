<?php

namespace Tests\PrimeEvents\TestFiles;

use Bdf\Prime\Entity\Model;

class OtherEntity extends Model
{
    public $id;

    /**
     * OtherEntity constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }
}
