<?php

namespace App\Invision;


class Invision
{
    /**
     * Singleton instance
     * @var Invision
     */
    private static $instance;

    /**
     * Path to the IPS installation
     * @var string
     */
    protected $path;

    public function __construct( $path )
    {
        $this->path = $path;
        putenv( 'IDH_COMMAND=ENABLED' );
        require_once $this->path . '/' . 'init.php';
    }

    /**
     * Singleton initializer
     * @param $path
     * @return Invision
     */
    public static function i( $path ): Invision
    {
        return static::$instance ?: new static( $path );
    }

    /**
     * Return a resolved language string
     * @param $key
     * @return array|string
     */
    public function lang( $key ): string
    {
        return \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( $key );
    }

    /**
     * Clear cache files
     */
    public function clearCache(): void
    {
        \IPS\Data\Cache::i()->clearAll();
    }

    /**
     * Clear data storage
     */
    public function clearDataStore(): void
    {
        \IPS\Data\Store::i()->clearAll();
    }

    /**
     * Clear compiled JS files
     */
    public function clearJsFiles(): void
    {
        \IPS\Output::clearJsFiles();
    }

    /**
     * Clear compiled CSS files
     */
    public function clearCompiledCss(): void
    {
        \IPS\Theme::deleteCompiledCss();
    }
}