<?php

namespace App\Commands;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use PhpSchool\CliMenu\CliMenu;

class Apps extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'apps';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'View and manage installed IPS applications';

    /**
     * @var \IPS\Application
     */
    protected $app;

    /**
     * @var string
     */
    protected $appName;

    /**
     * @var CliMenu
     */
    protected $menu;

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \PhpSchool\CliMenu\Exception\InvalidTerminalException
     */
    public function handle()
    {
        /** @var \App\Invision\Invision $ips */
        $ips = app( Invision::class );
        $ips->init();

//        dd( \IPS\Application::applications() );

        $option = $this->menu( 'Applications', array_keys( \IPS\Application::applications() ) )->open();
        $this->app = array_values( \IPS\Application::applications() )[ $option ];

        $this->menu( $this->appName = $ips->lang( '__app_' . $this->app->directory ) )
            ->addItem( 'Information', [$this, 'handleResponse'], FALSE, FALSE )
            ->addItem( 'Rebuild', [$this, 'handleResponse'], FALSE, FALSE )
            ->addItem( 'Build new version', [$this, 'handleResponse'], $this->isInvisionApp(), $this->isInvisionApp() )
//            ->addItem( 'Build testing environment', [$this, 'handleResponse'], $this->isInvisionApp(), $this->isInvisionApp() )
            ->addItem( $this->getToggleOption(), [$this, 'handleResponse'], $this->app->protected, $this->app->protected )
            ->addItem( 'Build for release', [$this, 'handleResponse'], $this->isInvisionApp(), $this->isInvisionApp() )
            ->setItemExtra( '[Disabled]' )
            ->open();

        //dd($apps);
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule( Schedule $schedule ): void
    {
        // $schedule->command(static::class)->everyMinute();
    }

    public function handleResponse( CliMenu $menu )
    {
        $selection = $menu->getSelectedItem()->getText();

        if ( $selection === 'Information' )
        {
            $menu->close();
            $this->printAppInfo( $this->appName );
        }


        if ( $selection === $this->getToggleOption() )
        {
            $this->toggleApp();
            $menu->confirm( "Application {$selection}d" )->display('Ok');
            $menu->close();
        }

        if ( $selection === 'Rebuild' )
        {
            $this->app->build();
            $menu->confirm( "Build {$this->app->version} successful" )->display('Ok');
            $menu->close();
        }

        if ( $selection === 'Build new version' )
        {
            list( $human, $long ) = $this->getNewVersion();

            $human = $menu->askText()->setPromptText( 'Human version' )->setPlaceholderText( $human )->ask();
            $long  = $menu->askText()->setPromptText( 'Long version' )->setPlaceholderText( $long )->ask();

            $this->app->assignNewVersion( $long->fetch(), $human->fetch() );
            $this->app->build();

            $menu->confirm( "Build {$this->app->version} successful" )->display('Ok');
        }

        if ( $selection === 'Build for release' )
        {
            $this->buildForRelease();

            $menu->confirm( "Application {$this->appName} (v{$this->app->version}) built successfully" )->display( 'Ok' );
        }
    }

    /**
     * Print application info the console
     * @param   string  $appName
     */
    protected function printAppInfo( $appName )
    {
        $this->info( "<options=bold>Application name:</> {$appName}" );
        $this->info( "<options=bold>Installation directory:</> {$this->app->directory}" );
        $this->info( "<options=bold>Installation date:</> " . date( 'F jS, Y', $this->app->added ) );
        $this->info( "<options=bold>Version:</> {$this->app->version} ({$this->app->long_version})" );
        $this->info( "<options=bold>Author:</> {$this->app->author}" );
        $this->info( "<options=bold>Website:</> {$this->app->website}" );
    }

    /**
     * Enable or Disable the application
     */
    protected function toggleApp()
    {
        if ( $this->app->enabled )
        {
            $this->app->enabled = FALSE;
//            \IPS\Session::i()->log( 'acplog__node_disabled', array( $this->app->title => TRUE, $this->app->titleForLog() => FALSE ) );
        }
        else
        {
            $this->app->enabled = TRUE;
//            \IPS\Session::i()->log( 'acplog__node_enabled', array( $this->app->title => TRUE, $this->app->titleForLog() => FALSE ) );
        }

        $this->app->save();

        \IPS\Data\Store::i()->clearAll();
        \IPS\Data\Cache::i()->clearAll();
    }

    /**
     * Calculate version numbers for a new build
     * @return array
     */
    protected function getNewVersion()
    {
        // No version set yet?
        if ( !$this->app->version )
        {
            return [ '1.0.0', 10000 ];
        }

        $exploded = explode( '.', $this->app->version );

        $human = "{$exploded[0]}.{$exploded[1]}." . ( \intval( $exploded[2] ) + 1 );
        $long  = $this->app->long_version + 1;

        return [$human, $long];
    }

    /**
     * Build an application for release
     */
    public function buildForRelease()
    {
        $this->app->build();

        $pharDir = rtrim( config('invision.builds_path'), \DIRECTORY_SEPARATOR ) . \DIRECTORY_SEPARATOR . $this->appName;

        if ( !file_exists( $pharDir ) )
        {
            mkdir( $pharDir );
        }

        $pharPath = $pharDir . \DIRECTORY_SEPARATOR . $this->app->directory . '.tar';

        $download = new \PharData( $pharPath, 0, $this->app->directory . ".tar", \Phar::TAR );
        $download->buildFromIterator( new \IPS\Application\BuilderIterator( $this->app ) );
    }

    /**
     * Checks whether or not the specific app is owned by IPS
     * @return bool
     */
    protected function isInvisionApp()
    {
        // We can't just rely on the author since _someone_ forgot to set the author for Blog
        return in_array( $this->app->directory, ['core', 'forums', 'downloads', 'blog', 'gallery', 'calendar', 'cms', 'nexus'] );
    }

    protected function getToggleOption()
    {
        return $this->app->enabled ? 'Disable' : 'Enable';
    }
}
