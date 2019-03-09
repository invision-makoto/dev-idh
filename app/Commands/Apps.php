<?php

namespace App\Commands;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var \App\Invision\Invision $ips */
        $ips = app( Invision::class );
        $ips->init();

//        dd( \IPS\Application::applications() );

        $option = $this->menu( 'Applications', array_keys( \IPS\Application::applications() ) )->open();
        $application = array_values( \IPS\Application::applications() )[ $option ];

        $option = $this->menu( $appName = $ips->lang( '__app_' . $application->directory ), [
            'Information',
            'Rebuild',
            'Build new version',
            'Build testing environment',
            'Enable / disable',
            'Uninstall'
        ] )->open();

        if ( $option === 0 )
        {
            $this->info( "<options=bold>Application name:</> {$appName}" );
            $this->info( "<options=bold>Installation directory:</> {$application->directory}" );
            $this->info( "<options=bold>Installation date:</> " . date( 'F jS, Y', $application->added ) );
            $this->info( "<options=bold>Version:</> {$application->version} ({$application->long_version})" );
            $this->info( "<options=bold>Author:</> {$application->author}" );
            $this->info( "<options=bold>Website:</> {$application->website}" );
        }

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
}
