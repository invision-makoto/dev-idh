<?php

namespace App\Commands\IPS;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Requests;

class Download extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'ips:download
    {--license= : Your IPS license key}
    {--development : Download development builds?}
    {--user= : Your IPS client area username}
    {--pass= : Your IPS client area password}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Download the latest release of IPS';

    /**
     * Download key
     * @var string
     */
    protected $downloadKey;

    /**
     * IPS remote services URL
     * @var string
     */
    protected static $remoteServices = 'https://remoteservices.invisionpower.com/';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $license = $this->option( 'license' ) ?? env( 'IPS_LICENSE' ) ?? $this->ask( 'What is your IPS license key?' );
        $user    = $this->option( 'user' ) ?? env( 'IPS_USER' ) ?? $this->ask( 'What is your IPS client area username?' );
        $pass    = $this->option( 'pass' ) ?? env( 'IPS_PASS' ) ?? $this->secret( 'What is your IPS client area password?' );

        $this->task( 'Requesting download key', function () use ( $user, $pass, $license ) {
            // Grab our download key
            $options = [ 'auth' => [ $user, $pass ], 'verify' => TRUE ];
            $data    = [ 'files' => '', 'development' => (int)$this->option( 'development' ) ];
            $request = Requests::request( static::$remoteServices . "/build/{$license}/", [], $data, Requests::GET, $options );

            // Bad login?
            if ( $request->status_code !== 200 )
            {
                $this->line( '' );
                $this->error( $request->body );
                exit( 1 );
            }

            $this->downloadKey = $request->body;
        } );

        // Now let's make the actual download request
        $this->task( 'Executing download request', function () {
            $request = Requests::request( static::$remoteServices . "/download/{$this->downloadKey}", [], [], Requests::GET, [ 'verify' => true ] );

            if ( $request->status_code !== 200 )
            {
                $this->line( '' );
                $this->error( $request->body );
                exit( 1 );
            }

            \file_put_contents( 'Invision Community.zip', $request->body );
        } );

        $this->info( 'Download successful!' );
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
