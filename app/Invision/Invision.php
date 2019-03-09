<?php

namespace App\Invision;


class Invision
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function init()
    {
        require_once $this->path . '/' . 'init.php';
    }
}