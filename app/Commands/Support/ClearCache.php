<?php

namespace App\Commands\Support;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ClearCache extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'support:recache';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Manually clear all IPS cache files';

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

        $this->task( 'Clearing javascript files', function () {
            $this->ips->clearJsFiles();
        } );

        $this->task( 'Clearing compiled CSS files', function () {
            $this->ips->clearCompiledCss();
        } );

        $this->task( 'Clearing compiled templates', function () {
            $this->ips->clearCompiledTemplates();
        } );

        $this->task( 'Clearing cache files', function () {
            $this->ips->clearCache();
            \IPS\Member::clearCreateMenu();
        } );

        $this->task( 'Clearing data store', function () {
            $this->ips->clearDataStore();
        } );

        $this->task( 'Clearing \IPS\Helpers\Wizard AdminCP sessions', function () {
            $this->ips->clearWizardSessions();
        } );
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
