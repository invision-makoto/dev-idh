<?php

namespace App\Commands;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Psy\Configuration;
use Psy\Shell;

class Console extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'console 
                            {--member=1 : ID of the member that \IPS\Member::loggedIn() should return (optional)}
                            {--guest : Shorthand for --member=0 (optional)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Launches an interactive PHP shell interpreter for IPS';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ips = app( Invision::class );

        $opts = $this->options();

        if ( $opts['guest'] )
        {
            putenv( 'IDH_MEMBER_ID=0' );
        }
        else
        {
            putenv( "IDH_MEMBER_ID={$opts['member']}" );
        }

        $config = new Configuration( [ 'startupMessage' => 'Welcome to the IDH console! Need assistance? Run "help"' ] );
        $shell  = new Shell( $config );

        $shell->run();
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
