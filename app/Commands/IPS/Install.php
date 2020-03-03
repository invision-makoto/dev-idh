<?php

namespace App\Commands\IPS;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class Install extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'ips:install
                            {license : The license key to use for this installation (required)}
                            {admin-username : Username for the primary admin account (required)}
                            {admin-email : E-Mail address for the primary admin account (required)}
                            {admin-password : Password for the primary admin account (required)}
                            {base-url : The base URL the installation is located at (e.g. http://localhost/ or http://invision.local/) (required)}
                            {--db-user=root : Database username (optional)}
                            {--db-pass= : Database password (optional)}
                            {--db-host=127.0.0.1 : Database hostname (optional)}
                            {--db-port=3306 : Database hostname (optional)}
                            {--db-name=ips4_dev : Database name (optional)}
                            {--path= : Path to the IPS installation if not installing from the current path (optional)}
                            {--test-mode : Configures the server for acceptance testing compatibility (optional)}
                            {--friendly-urls : Enable and configure friendly URL\'s immediately after installation (optional)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Perform a fresh IPS installation from the commandline';

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
        $path = $this->option( 'path' ) ?: \getcwd();

        // Get the configuration file ready
        if ( !file_exists( $this->path( $path, 'conf_global.php' ) ) )
        {
            try
            {
                @rename( $this->path( $path, 'conf_global.dist.php' ), $this->path( $path, 'conf_global.php' ) );
            }
            catch ( \Exception $e ) {}

            if ( !file_exists( $this->path( $path, 'conf_global.php' ) ) )
            {
                try
                {
                    @file_put_contents( $this->path( $path, 'conf_global.php' ), '' );
                }
                catch ( \Exception $e )
                {
                }
            }
        }

        /** @noinspection PhpIncludeInspection */
        require $this->path( $path, 'conf_global.php' );
        if ( isset( $INFO, $INFO['installed'] ) )
        {
            $this->error('The installer is locked. Either an installation has already been performed, or failed. Please delete your conf_global.php file and empty any databases before trying again.');
            exit;
        }

        // Get ready for installation!
        \IPS\Dispatcher\Setup::i();

        $db = \IPS\Db::i( '__init__', [
            'sql_host'      => $this->option('db-host'),
            'sql_port'      => $this->option('db-port'),
            'sql_user'      => $this->option('db-user'),
            'sql_pass'      => $this->option('db-pass'),
            'sql_database'  => 'INFORMATION_SCHEMA'
        ] );

        $db->createDatabase( $this->option('db-name') );

        // Write the configuration
        $confGlobal = [
            'sql_host'          => $this->option('db-host'),
            'sql_database'      => $this->option('db-name'),
            'sql_user'          => $this->option('db-user'),
            'sql_pass'          => $this->option('db-pass'),
            'sql_port'          => $this->option('db-port'),
            'sql_socket'        => NULL,
            'sql_tbl_prefix'    => NULL,
            'sql_utf8mb4'       => TRUE,
            'board_start'       => NULL,
            'installed'         => FALSE,
            'base_url'          => $this->argument('base-url'),
            'guest_group'       => 2,
            'member_group'      => 3,
            'admin_group'       => 4
        ];
        \file_put_contents( $this->path( $path, 'conf_global.php' ), "<?php\n\n" . '$INFO = ' . var_export( $confGlobal, TRUE ) . ';' );

        // Get a list of applications to install
        $apps = ['core'];
        foreach ( new \DirectoryIterator( $this->path( $path, 'applications' ) ) as $app )
        {
            if ( mb_substr( $app->getBasename(), 0, 1 ) !== '.' and $app->isDir() and $app->getBasename() != 'core' )
            {
                $apps[] = $app->getBasename();
            }
        }
        $apps = implode( ',', $apps );

        // Run the installation! This is surprisingly easy.
        $dbDetails = [
            'sql_host'      => $this->option('db-host'),
            'sql_user'      => $this->option('db-user'),
            'sql_pass'      => $this->option('db-pass'),
            'sql_database'  => $this->option('db-name'),
            'sql_utf8mb4'   => TRUE
        ];
        $installer = new \IPS\core\Setup\Install(
            explode( ',', $apps ),
            'forums',
            $this->argument('base-url'),
            $path,
            $dbDetails,
            $this->argument('admin-username'),
            $this->argument('admin-password'),
            $this->argument('admin-email'),
            $this->argument('license')
        );

        $data = 0;
        $lastMessage = NULL;
        while ( $data !== NULL )
        {
            $return = $installer->process( $data );
            $data = $return[0];

            if ( $return[1] != $lastMessage )
            {
                $this->info($return[1]);
                $lastMessage = $return[1];
            }
        }

        // Update the post-install configuration
        $confGlobal['installed'] = TRUE;
        $confGlobal['board_start'] = time();
        \file_put_contents( $this->path( $path, 'conf_global.php' ), "<?php\n\n" . '$INFO = ' . var_export( $confGlobal, TRUE ) . ';' );

        // Enable friendly URL's?
        if ( $this->option('friendly-urls') )
        {
            \IPS\Db::i()->update( 'core_sys_conf_settings', [ 'conf_value' => 1 ], [ 'conf_key=?', 'htaccess_mod_rewrite' ] );
            \file_put_contents( $this->path( $path, '.htaccess' ), "<IfModule mod_rewrite.c>
Options -MultiViews
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule \.(js|css|jpeg|jpg|gif|png|ico|map)(\?|$) /404error.php [L,NC]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
" );
        }

        if ( $this->option('test-mode') )
        {
            // Database storage is needed when running acceptance tests
            \file_put_contents( $this->path( $path, 'constants.php' ), "<?php

\define( 'REDIS_ENABLED', false );
\define( 'STORE_METHOD', 'Database' );
\define( 'STORE_CONFIG', '[]' );
\define( 'CACHE_METHOD', 'None' );
\define( 'CACHE_CONFIG', '[]' );
\define( 'CACHE_PAGE_TIMEOUT', 0 );
\define( 'SUITE_UNIQUE_KEY', '88e527d286' );

" );

            // We also need to disable recaptcha
            \IPS\Db::i()->update( 'core_sys_conf_settings', [ 'conf_value' => 'none' ], [ 'conf_key=?', 'bot_antispam_type' ] );
        }


        // Finalize some things
        \IPS\Db::i()->update( 'core_sys_conf_settings', [ 'conf_value' => $this->argument('license') ], [ 'conf_key=?', 'ipb_reg_number' ] );

        foreach( \IPS\Application::applications() as $app => $data )
        {
            \IPS\Theme::deleteCompiledTemplate( $app);
            \IPS\Theme::deleteCompiledCss( $app );
            \IPS\Theme::deleteCompiledResources( $app );
        }
        \IPS\Output::clearJsFiles();

        \IPS\core\FrontNavigation::i()->buildDefaultFrontNavigation();

        unset( \IPS\Data\Store::i()->settings );

        $this->info('Installation successful!');
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

    public function path()
    {
        $paths = \func_get_args();
        return \join( \DIRECTORY_SEPARATOR, $paths );
    }
}
