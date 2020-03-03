<?php

namespace App\Commands\Apps;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

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
