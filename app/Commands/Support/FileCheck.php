<?php

namespace App\Commands\Support;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class FileCheck extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'support:filecheck';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Detect and list any modified core IPS files';

    /**
     * @var \App\Invision\Invision
     */
    protected $ips;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->ips = app( Invision::class );

        $modified = [];
        $this->task( 'Running MD5 checks', function () use ( &$modified ) {
            $modified = \IPS\Application::md5Check();
        } );
        $this->line('');

        if ( $modified )
        {
            $this->warn( 'MD5 checks failed on the following files' );
            $this->info( '----------------------------------------' );
            foreach ( $modified as $file )
            {
                $this->warn( "Modified: {$file}" );
            }

            return;
        }

        $this->info( 'MD5 checks completed without error!' );
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
