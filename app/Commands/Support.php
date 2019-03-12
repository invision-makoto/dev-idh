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
             ->addItem( 'Dump MySQL database', [ $this, 'sqlDump' ], FALSE, FALSE )
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
    public function clearCache( CliMenu $menu ): void
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
    public function runMd5Checks( CliMenu $menu ): void
    {
        $menu->close();

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
     * MySQL dump the IPS database
     * @param CliMenu $menu
     * @throws \PhpSchool\CliMenu\Exception\InvalidTerminalException
     */
    public function sqlDump( CliMenu $menu ): void
    {
        $menu->close();

        $conf = $this->ips->getConfig();
        $backupsPath = config( 'invision.backups_path' ) . \DIRECTORY_SEPARATOR . time();

        $this->task( 'Creating backups directory', function () use ( $backupsPath ) {
            mkdir( $backupsPath, 0777, TRUE );
        } );

        $this->task( 'Running MySQL dump', function () use ( $conf, $backupsPath ) {
            \Spatie\DbDumper\Databases\MySql::create()
                                            ->setHost( $conf['sql_host'] )
                                            ->setDbName( $conf['sql_database'] )
                                            ->setUserName( $conf['sql_user'] )
                                            ->setPassword( $conf['sql_pass'] )
                                            ->setPort( $conf['sql_port'] )
                                            ->useExtendedInserts()
                                            ->dumpToFile( $backupsPath . \DIRECTORY_SEPARATOR . $conf['sql_database'] . '.sql' );
        } );
    }
}
