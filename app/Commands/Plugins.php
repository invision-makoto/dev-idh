<?php

namespace App\Commands;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use PhpSchool\CliMenu\CliMenu;

class Plugins extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'plugins';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'View and manage installed IPS plugins';

    /**
     * @var \IPS\Plugin
     */
    protected $plugin;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var \App\Invision\Invision $ips */
        $ips = app( Invision::class );

        $plugins = [];
        foreach ( \IPS\Plugin::plugins() as $plugin )
        {
            $plugins[ $plugin->name ] = $plugin;
        }

        $option = $this->menu( 'Plugins', array_keys( $plugins ) )->open();
        $this->plugin = array_values( $plugins )[ $option ]; // This is kind of dumb, I know.

        $this->menu( $this->plugin->name )
             ->addItem( 'Information', [$this, 'pluginInformation'] )
//             ->addItem( 'Build for release', [$this, 'buildForRelease'] )
             ->addItem( $this->getToggleOption(), [$this, 'toggleEnabled'] )
             ->setItemExtra( '[Disabled]' )
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

    public function pluginInformation( CliMenu $menu ): void
    {
        $menu->close();

        $this->info( "<options=bold>Plugin name:</> {$this->plugin->name}" );
        $this->info( "<options=bold>Plugin location:</> {$this->plugin->location}" );
        $this->info( "<options=bold>Version:</> {$this->plugin->version_human} ({$this->plugin->version_long})" );
        $this->info( "<options=bold>Author:</> {$this->plugin->author}" );
        $this->info( "<options=bold>Website:</> {$this->plugin->website}" );
    }

    /**
     * @TODO Copying the entire build method for plugins isn't sane and has licensing issues. We should look into
     *       utilizing a hook to make it so we can intercept download requests and save the files locally instead
     */
    public function buildForRelease( CliMenu $menu ): void
    {
        // TODO
    }

    public function toggleEnabled( CliMenu $menu ): void
    {
        $status = $this->getToggleOption();

        if ( $this->plugin->enabled )
        {
            $this->plugin->enabled = FALSE;
        }

        if ( $this->plugin->enabled = TRUE )
        {
            $this->plugin->enabled = FALSE;
        }

        $this->plugin->save();

        \IPS\Data\Store::i()->clearAll();
        \IPS\Data\Cache::i()->clearAll();

        $menu->confirm( "Plugin {$status}d" )->display('Ok');
        $menu->close();
    }

    /**
     * Show whether the application is enabled or disabled
     * @return string
     */
    protected function getToggleOption(): string
    {
        return $this->plugin->enabled ? 'Disable' : 'Enable';
    }
}
