<?php

namespace App\Invision;


class Invision
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
        putenv( 'IDH_COMMAND=ENABLED' );
    }

    public function init()
    {
        require_once $this->path . '/' . 'init.php';
    }

    public function lang($key)
    {
        return \IPS\Lang::load(\IPS\Lang::defaultLanguage())->get($key);
    }
}