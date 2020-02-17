<?php

namespace App\Invision;


class Invision
{
    /**
     * Path to the IPS installation
     * @var string
     */
    protected $path;

    /**
     * Invision constructor.
     *
     * @param $path
     */
    public function __construct( $path )
    {
        $this->path = $path;
        putenv( 'IDH_COMMAND=ENABLED' );
        require_once $this->path . '/' . 'init.php';
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

    /**
     * Clear active \IPS\Helpers\Wizard sessions
     */
    public function clearWizardSessions(): void
    {
        \IPS\Db::i()->update( 'core_sys_cp_sessions', [ 'session_app_data' => '' ], [ \IPS\Db::i()->like( 'session_app_data', 'wizard-', TRUE, TRUE, TRUE ) ] );
    }

    /**
     * Load and return IPS configuration variables
     * @return array
     */
    public function getConfig()
    {
        require $this->path . '/' . 'conf_global.php';

        /** @noinspection PhpUndefinedVariableInspection */
        return $INFO;
    }
}
