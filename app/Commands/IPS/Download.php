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
     * IPS remote services URL
     * @var string
     */
    protected static $remoteServices = 'https://remoteservices.invisionpower.com/';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Requests_Exception
     */
    public function handle()
    {
        $license = $this->option( 'license' ) ?? env( 'IPS_LICENSE' ) ?? $this->ask( 'What is your IPS license key?' );
        $user    = $this->option( 'user' ) ?? env( 'IPS_USER' ) ?? $this->ask( 'What is your IPS client area username?' );
        $pass    = $this->option( 'pass' ) ?? env( 'IPS_PASS' ) ?? $this->secret( 'What is your IPS client area password?' );

        // Grab our download key
        $options = [ 'auth' => [ $user, $pass ] ];
        $data    = [ 'files' => '', 'development' => (int)$this->option( 'development' ) ];
        $request = Requests::request( static::$remoteServices . "/build/{$license}/", [], $data, Requests::GET, $options );

        // Bad login?
        if ( $request->status_code === 401 )
        {
            $this->error( $request->body );
            exit( 1 );
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
