<?php

namespace App\Commands\Support;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class SysLog extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'support:syslog';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display the last 10 entries made to the system logs table';

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

        // Get the last 10 system logs
        $logs = \iterator_to_array(\IPS\Db::i()->select('*', 'core_log', NULL, 'id DESC', 10));
        if ( empty($logs) )
        {
            $this->warn("Nothing has been written to the core_log table");
            return;
        }

        // Prompt for one to return information on
        $menu = $this->menu( 'System error logs' );
        foreach ( $logs as $key => $log )
        {
            $message = "{$log['exception_class']}  ({$log['category']})";
            $menu->addOption($key, $message);
        }

        $option = $menu->open();
        $log = $logs[ $option ];
        \dd($log);
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
