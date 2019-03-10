<?php

namespace App\Commands;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use PhpSchool\CliMenu\CliMenu;
use Symfony\Component\Filesystem\Filesystem;

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
     * Valid files to include in the documentation archive
     * @var array
     */
    protected static $docFiles = [ 'README.md', 'README.txt', 'README.htm', 'README.html', 'LICENSE', 'LICENSE.txt' ];

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

    /**
     * CUI action callback method
     * @param CliMenu $menu
     * @throws \PhpSchool\CliMenu\Exception\InvalidTerminalException
     */
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
            $menu->close();

            $this->buildForRelease();

//            $menu->confirm( "Application {$this->appName} (v{$this->app->version}) built successfully" )->display( 'Ok' );
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
        $this->task( 'Rebuilding application', function () {
            $this->app->build();
        } );

        $buildDir = rtrim( config('invision.builds_path'), \DIRECTORY_SEPARATOR ) . \DIRECTORY_SEPARATOR . $this->appName . \DIRECTORY_SEPARATOR . $this->app->long_version;
        if ( !file_exists( $buildDir ) )
        {
            $this->task( "Creating new build directory {$buildDir}", function () use ( $buildDir ) {
                mkdir( $buildDir, 0777, TRUE );
            } );
        }

        $pharPath = $buildDir . \DIRECTORY_SEPARATOR . $this->app->directory . '.tar';
        if ( file_exists( $pharPath ) )
        {
            $this->task( 'Removing previous build', function () use ( $pharPath ) {
                unlink( $pharPath );
            } );
        }
        $this->task( 'Building application PHAR archive', function () use ( $pharPath ) {
            $download = new \PharData( $pharPath, 0, $this->app->directory . ".tar", \Phar::TAR );
            $download->buildFromIterator( new \IPS\Application\BuilderIterator( $this->app ) );
        } );

        // Any documentation / license files?
        $appPath = config( 'invision.path' ) . \DIRECTORY_SEPARATOR . 'applications' . \DIRECTORY_SEPARATOR . $this->app->directory;

        $hasDocs = FALSE;
        foreach ( static::$docFiles as $filename )
        {
            if ( file_exists( $appPath . \DIRECTORY_SEPARATOR . $filename ) )
            {
                $hasDocs = TRUE;
                break;
            }
        }

        if ( $hasDocs )
        {
            $this->task( 'Compiling documentation and license files', function () use ( $buildDir, $appPath ) {
                $docPath = $buildDir . \DIRECTORY_SEPARATOR . 'Documentation and License.zip';
                $zip = new \ZipArchive();
                $zip->open( $docPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

                foreach ( static::$docFiles as $filename )
                {
                    if ( file_exists( $appPath . \DIRECTORY_SEPARATOR . $filename ) )
                    {
                        $zip->addFile( $appPath . \DIRECTORY_SEPARATOR . $filename, $filename );
                    }
                }

                $zip->close();
            } );
        }

        // Now bundle up our development resources
        $this->task( 'Compiling development resources', function () use ( $buildDir, $appPath ) {
            $devPath = $buildDir . \DIRECTORY_SEPARATOR . 'Development Resources.zip';
            $this->recursiveZip( $appPath . \DIRECTORY_SEPARATOR . 'dev', $devPath );
        } );

        // Copy screenshots, if we have them
        $fs = new Filesystem();
        $screenshotsPath = $appPath . \DIRECTORY_SEPARATOR . 'screenshots';

        if ( $fs->exists( $screenshotsPath ) )
        {
            $this->task( 'Copying screenshots', function () use ( $fs, $screenshotsPath, $buildDir ) {
                $fs->mirror( $screenshotsPath, $buildDir . \DIRECTORY_SEPARATOR . 'screenshots' );
            } );
        }
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

    /**
     * Show whether the application is enabled or disabled
     * @return string
     */
    protected function getToggleOption()
    {
        return $this->app->enabled ? 'Disable' : 'Enable';
    }

    /**
     * Recursively zip a specified directory
     * @example https://stackoverflow.com/questions/1334613/how-to-recursively-zip-a-directory-in-php
     * @param $source
     * @param $destination
     * @return bool
     */
    protected function recursiveZip( $source, $destination )
    {
        $zip = new \ZipArchive();
        $zip->open( $destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

        $source = \str_replace( '\\', '/', \realpath( $source ) );

        if ( \is_dir( $source ) === TRUE )
        {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $source ), \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $files as $file )
            {
                $file = \str_replace( '\\', '/', $file );

                // Ignore "." and ".." folders
                if ( in_array( \substr( $file, \strrpos( $file, '/' ) + 1 ), [ '.', '..' ] ) )
                {
                    continue;
                }

                $file = \realpath( $file );

                if ( \is_dir( $file ) === TRUE )
                {
                    $zip->addEmptyDir( \str_replace( $source . '/', '', $file . '/' ) );
                }
                else
                {
                    if ( is_file( $file ) === TRUE )
                    {
                        $zip->addFromString( \str_replace( $source . '/', '', $file ), \file_get_contents( $file ) );
                    }
                }
            }
        }
        else
        {
            if ( \is_file( $source ) === TRUE )
            {
                $zip->addFromString( \basename( $source ), \file_get_contents( $source ) );
            }
        }

        return $zip->close();
    }
}
