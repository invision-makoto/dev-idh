<?php

namespace App\Commands\Apps;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Filesystem\Filesystem;

class Build extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'apps:build
                            {application : The application directory (required)}
                            {--rebuild-only : Rebuilds the application, but does not package it for release (optional)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Build an application and package it for release';

    /**
     * @var \IPS\Application
     */
    protected $app;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var Invision $ips */
        $ips = app( Invision::class );
        $apps = \IPS\Application::applications();

        // Make sure the application exists
        $app = \strtolower( $this->argument( 'application' ) );
        if ( !isset( $apps[ $app ] ) )
        {
            $this->error( "Application directory {$app} not found. Please check your spelling and try again." );
            return;
        }
        $this->app = $apps[ $app ];

        // Rebuild
        $this->task( 'Rebuilding application', function () {
            $this->app->build();
        } );

        // If that's all we wanted, exit now
        if ( $this->option( 'rebuild-only' ) )
        {
            $this->info( "Application {$app} rebuilt successfully." );
            return;
        }

        $this->buildForRelease( $ips );
    }

    /**
     * Build an application for release
     *
     * @param Invision $ips
     */
    public function buildForRelease( $ips )
    {
        $appName = $ips->lang( '__app_' . $this->app->directory );
        $buildDir = rtrim( config('invision.builds_path'), \DIRECTORY_SEPARATOR ) . \DIRECTORY_SEPARATOR . $appName . \DIRECTORY_SEPARATOR . $this->app->long_version;
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
        foreach ( \App\Commands\Apps\Apps::$docFiles as $filename )
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

                foreach ( \App\Commands\Apps\Apps::$docFiles as $filename )
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
            \App\Commands\Apps\Apps::recursiveZip( $appPath . \DIRECTORY_SEPARATOR . 'dev', $devPath );
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
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
