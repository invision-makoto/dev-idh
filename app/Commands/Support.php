<?php

namespace App\Commands;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use PhpSchool\CliMenu\CliMenu;

class Support extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'support';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Having issues? The support utility is here to help!';

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

        $this->menu( 'Support tools' )
             ->addItem( 'Clear cache', [ $this, 'clearCache' ], FALSE, FALSE )
             ->addItem( 'Run MD5 checks', [ $this, 'runMd5Checks' ], FALSE, FALSE )
             ->open();
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

    /**
     * Clear IPS caches
     * @param CliMenu $menu
     * @throws \PhpSchool\CliMenu\Exception\InvalidTerminalException
     */
    protected function clearCache( CliMenu $menu ): void
    {
        $menu->close();

        $this->task( 'Clearing javascript files', function () {
            $this->ips->clearJsFiles();
        } );

        $this->task( 'Clearing compiled CSS files', function () {
            $this->ips->clearJsFiles();
        } );

        $this->task( 'Clearing cache files', function () {
            $this->ips->clearCache();
            \IPS\Member::clearCreateMenu();
        } );

        $this->task( 'Clearing data store', function () {
            $this->ips->clearDataStore();
        } );
    }

    /**
     * Run MD5 checks on all IPS files to find source code that has been modified directly
     * @param CliMenu $menu
     * @throws \PhpSchool\CliMenu\Exception\InvalidTerminalException
     */
    public function runMd5Checks( CliMenu $menu )
    {
        $menu->close();

        $modified = [];
        $this->task( 'Running MD5 checks', function () use ( &$modified ) {
            $modified = \IPS\Application::md5Check();
        } );
        $this->line('');

        $this->warn( 'MD5 checks failed on the following files' );
        $this->info( '----------------------------------------' );
        foreach ( $modified as $file )
        {
            $this->warn( "Modified: {$file}" );
        }
    }
}
