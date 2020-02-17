<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Open\Open;

class Patreon extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'patreon';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Help support Invision Dev Helper by contributing on Patreon!';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
//        Open::open('https://www.patreon.com/makotodev');
        $this->info('Thank you for your interest in supporting this project!');
        $this->info('https://www.patreon.com/makotodev');
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
