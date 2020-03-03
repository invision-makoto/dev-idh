<?php

namespace App\Commands\Apps;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use IPS\Xml\SimpleXML;
use LaravelZero\Framework\Commands\Command;
use PhpSchool\CliMenu\CliMenu;
use Symfony\Component\Filesystem\Filesystem;

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
     * @var \IPS\Application
     */
    protected $app;

    /**
     * @var string
     */
    protected $appName;

    /**
     * @var CliMenu
     */
    protected $menu;

    /**
     * Valid files to include in the documentation archive
     * @var array
     */
    public static $docFiles = [ 'README.md', 'README.txt', 'README.htm', 'README.html', 'LICENSE', 'LICENSE.txt' ];

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \PhpSchool\CliMenu\Exception\InvalidTerminalException
     */
    public function handle()
    {
        /** @var \App\Invision\Invision $ips */
        $ips = app( Invision::class );

        $option = $this->menu( 'Applications', array_keys( \IPS\Application::applications() ) )->open();
        $this->app = array_values( \IPS\Application::applications() )[ $option ];

        $this->menu( $this->appName = $ips->lang( '__app_' . $this->app->directory ) )
            ->addItem( 'Information', [$this, 'handleResponse'], FALSE, FALSE )
            ->addItem( 'Build for release', [$this, 'handleResponse'], $this->isInvisionApp(), $this->isInvisionApp() )
            ->addItem( 'Rebuild', [$this, 'handleResponse'], FALSE, FALSE )
            ->addItem( 'Rebuild development resources', [$this, 'rebuildDevResources'], FALSE, FALSE )
            ->addItem( 'Build new version', [$this, 'handleResponse'], $this->isInvisionApp(), $this->isInvisionApp() )
//            ->addItem( 'Build testing environment', [$this, 'handleResponse'], $this->isInvisionApp(), $this->isInvisionApp() )
            ->addItem( $this->getToggleOption(), [$this, 'handleResponse'], $this->app->protected, $this->app->protected )
            ->setItemExtra( '[Disabled]' )
            ->open();
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

    /**
     * CUI action callback method
     * @param CliMenu $menu
     * @throws \PhpSchool\CliMenu\Exception\InvalidTerminalException
     */
    public function handleResponse( CliMenu $menu )
    {
        $selection = $menu->getSelectedItem()->getText();

        if ( $selection === 'Information' )
        {
            $menu->close();
            $this->printAppInfo( $this->appName );
        }


        if ( $selection === $this->getToggleOption() )
        {
            $this->toggleApp();
            $menu->confirm( "Application {$selection}d" )->display('Ok');
            $menu->close();
        }

        if ( $selection === 'Rebuild' )
        {
            $this->app->build();
            $menu->confirm( "Build {$this->app->version} successful" )->display('Ok');
            $menu->close();
        }

        if ( $selection === 'Build new version' )
        {
            list( $human, $long ) = $this->getNewVersion();

            $human = $menu->askText()->setPromptText( 'Human version' )->setPlaceholderText( $human )->ask();
            $long  = $menu->askText()->setPromptText( 'Long version' )->setPlaceholderText( $long )->ask();

            $this->app->assignNewVersion( $long->fetch(), $human->fetch() );
            $this->app->build();

            $menu->confirm( "Build {$this->app->version} successful" )->display('Ok');
        }

        if ( $selection === 'Rebuild development resources' )
        {
            $menu->close();
            $this->rebuildDevResources( $menu );
        }

        if ( $selection === 'Build for release' )
        {
            $menu->close();

            $this->buildForRelease();
        }
    }

    /**
     * Print application info the console
     * @param   string  $appName
     */
    protected function printAppInfo( $appName )
    {
        $this->info( "<options=bold>Application name:</> {$appName}" );
        $this->info( "<options=bold>Installation directory:</> {$this->app->directory}" );
        $this->info( "<options=bold>Installation date:</> " . date( 'F jS, Y', $this->app->added ) );
        $this->info( "<options=bold>Version:</> {$this->app->version} ({$this->app->long_version})" );
        $this->info( "<options=bold>Author:</> {$this->app->author}" );
        $this->info( "<options=bold>Website:</> {$this->app->website}" );
    }

    /**
     * Enable or Disable the application
     */
    protected function toggleApp()
    {
        if ( $this->app->enabled )
        {
            $this->app->enabled = FALSE;
//            \IPS\Session::i()->log( 'acplog__node_disabled', array( $this->app->title => TRUE, $this->app->titleForLog() => FALSE ) );
        }
        else
        {
            $this->app->enabled = TRUE;
//            \IPS\Session::i()->log( 'acplog__node_enabled', array( $this->app->title => TRUE, $this->app->titleForLog() => FALSE ) );
        }

        $this->app->save();

        \IPS\Data\Store::i()->clearAll();
        \IPS\Data\Cache::i()->clearAll();
    }

    /**
     * Calculate version numbers for a new build
     * @return array
     */
    protected function getNewVersion()
    {
        // No version set yet?
        if ( !$this->app->version )
        {
            return [ '1.0.0', 10000 ];
        }

        $exploded = explode( '.', $this->app->version );

        $human = "{$exploded[0]}.{$exploded[1]}." . ( \intval( $exploded[2] ) + 1 );
        $long  = $this->app->long_version + 1;

        return [$human, $long];
    }

    /**
     * Build an application for release
     */
    public function buildForRelease()
    {
        $this->task( 'Rebuilding application', function () {
            $this->app->build();
        } );

        $buildDir = rtrim( config('invision.builds_path'), \DIRECTORY_SEPARATOR ) . \DIRECTORY_SEPARATOR . $this->appName . \DIRECTORY_SEPARATOR . $this->app->long_version;
        if ( !file_exists( $buildDir ) )
        {
            $this->task( "Creating new build directory {$buildDir}", function () use ( $buildDir ) {
                mkdir( $buildDir, 0777, TRUE );
            } );
        }

        $pharPath = $buildDir . \DIRECTORY_SEPARATOR . $this->app->directory . '.tar';
        if ( file_exists( $pharPath ) )
        {
            $this->task( 'Removing previous build', function () use ( $pharPath ) {
                unlink( $pharPath );
            } );
        }
        $this->task( 'Building application PHAR archive', function () use ( $pharPath ) {
            $download = new \PharData( $pharPath, 0, $this->app->directory . ".tar", \Phar::TAR );
            $download->buildFromIterator( new \IPS\Application\BuilderIterator( $this->app ) );
        } );

        // Any documentation / license files?
        $appPath = config( 'invision.path' ) . \DIRECTORY_SEPARATOR . 'applications' . \DIRECTORY_SEPARATOR . $this->app->directory;

        $hasDocs = FALSE;
        foreach ( static::$docFiles as $filename )
        {
            if ( file_exists( $appPath . \DIRECTORY_SEPARATOR . $filename ) )
            {
                $hasDocs = TRUE;
                break;
            }
        }

        if ( $hasDocs )
        {
            $this->task( 'Compiling documentation and license files', function () use ( $buildDir, $appPath ) {
                $docPath = $buildDir . \DIRECTORY_SEPARATOR . 'Documentation and License.zip';
                $zip = new \ZipArchive();
                $zip->open( $docPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

                foreach ( static::$docFiles as $filename )
                {
                    if ( file_exists( $appPath . \DIRECTORY_SEPARATOR . $filename ) )
                    {
                        $zip->addFile( $appPath . \DIRECTORY_SEPARATOR . $filename, $filename );
                    }
                }

                $zip->close();
            } );
        }

        // Now bundle up our development resources
        $this->task( 'Compiling development resources', function () use ( $buildDir, $appPath ) {
            $devPath = $buildDir . \DIRECTORY_SEPARATOR . 'Development Resources.zip';
            static::recursiveZip( $appPath . \DIRECTORY_SEPARATOR . 'dev', $devPath );
        } );

        // Copy screenshots, if we have them
        $fs = new Filesystem();
        $screenshotsPath = $appPath . \DIRECTORY_SEPARATOR . 'screenshots';

        if ( $fs->exists( $screenshotsPath ) )
        {
            $this->task( 'Copying screenshots', function () use ( $fs, $screenshotsPath, $buildDir ) {
                $fs->mirror( $screenshotsPath, $buildDir . \DIRECTORY_SEPARATOR . 'screenshots' );
            } );
        }
    }

    public function rebuildDevResources( CliMenu $menu )
    {
        $menu->close();

        $appPath = join( \DIRECTORY_SEPARATOR, [ config( 'invision.path' ), 'applications', $this->app->directory ] );
        $devPath = join( \DIRECTORY_SEPARATOR, [ $appPath, 'dev' ] );
        $fs = new Filesystem();

        /* Don't try and use this out of laziness for IPS dev files. */
        if ( $this->isInvisionApp() )
        {
            $this->error( 'Need development resources for Invision apps? Download the official ones here:' );
            $this->error( 'https://invisioncommunity.com/files/file/7185-developer-tools/' );
            return;
        }

        /* Warn the user if we already have development resources available */
        if ( $fs->exists( $devPath ) )
        {
            $this->warn( 'WARNING: A development folder for this application already exists' );
            $this->warn( 'If you continue, these files will be deleted and replaced with the rebuilt versions' );
            $continue = $this->confirm( 'Are you sure you wish to continue?' );

            if ( $continue )
            {
                $devBkpPath = join( \DIRECTORY_SEPARATOR, [ $appPath, 'dev_bkp' ] );
                if ( !$fs->exists( $devBkpPath ) )
                {
                    $this->task( 'Backing up existing development resources', function () use ( $appPath, $devBkpPath, $devPath, $fs ) {
                        $fs->mirror( $devPath, $devBkpPath );
                    } );
                }
            }
            else
            {
                $this->info( 'Aborting' );
                exit;
            }
        }

        /**
         * INIT
         */
        $this->task( 'Initializing development directories', function () use ( $appPath, $devPath, $fs ) {
            if ( $fs->exists( $devPath ) )
            {
                $fs->remove( $devPath );
            }

            $fs->mkdir( $devPath );
            $fs->mkdir( join( \DIRECTORY_SEPARATOR, [ $devPath, 'css' ] ) );
            $fs->mkdir( join( \DIRECTORY_SEPARATOR, [ $devPath, 'email' ] ) );
            $fs->mkdir( join( \DIRECTORY_SEPARATOR, [ $devPath, 'html' ] ) );
            $fs->mkdir( join( \DIRECTORY_SEPARATOR, [ $devPath, 'js' ] ) );
            $fs->mkdir( join( \DIRECTORY_SEPARATOR, [ $devPath, 'resources' ] ) );

            $fs->touch( join( \DIRECTORY_SEPARATOR, [ $devPath, 'index.html' ] ) );
            $fs->touch( join( \DIRECTORY_SEPARATOR, [ $devPath, 'css', 'index.html' ] ) );
            $fs->touch( join( \DIRECTORY_SEPARATOR, [ $devPath, 'email', 'index.html' ] ) );
            $fs->touch( join( \DIRECTORY_SEPARATOR, [ $devPath, 'html', 'index.html' ] ) );
            $fs->touch( join( \DIRECTORY_SEPARATOR, [ $devPath, 'js', 'index.html' ] ) );
            $fs->touch( join( \DIRECTORY_SEPARATOR, [ $devPath, 'resources', 'index.html' ] ) );

            $fs->touch( join( \DIRECTORY_SEPARATOR, [ $devPath, 'lang.php' ] ) );
            $fs->touch( join( \DIRECTORY_SEPARATOR, [ $devPath, 'jslang.php' ] ) );
        } );

        /**
         * REBUILD LANGUAGE STRINGS
         */
        $lang = [];
        $jsLang = [];

        if ( $fs->exists( $langXmlPath = join( \DIRECTORY_SEPARATOR, [$appPath, 'data', 'lang.xml'] ) ) )
        {
            $this->task( 'Rebuilding language files', function () use ( $appPath, $devPath, $langXmlPath, $fs, &$lang, &$jsLang ) {
                $service = new \Sabre\Xml\Service();
                $service = $service->parse( file_get_contents( $langXmlPath ) );

                foreach ( $service[0]['value'] as $xml )
                {
                    if ( $xml['attributes']['js'] == 1 )
                    {
                        $jsLang[ $xml['attributes']['key'] ] = $xml['value'];
                        continue;
                    }

                    $lang[ $xml['attributes']['key'] ] = $xml['value'];
                }

                $langPhp = '<?php $lang = ' . var_export( $lang, TRUE ) . ';';
                $jsLangPhp = '<?php $lang = ' . var_export( $jsLang, TRUE ) . ';';

                file_put_contents( join( \DIRECTORY_SEPARATOR, [ $devPath, 'lang.php' ] ), $langPhp );
                file_put_contents( join( \DIRECTORY_SEPARATOR, [ $devPath, 'jslang.php' ] ), $jsLangPhp );
            } );
        }

        /**
         * REBUILD TEMPLATE FILES
         */
        if ( $fs->exists( $themeXmlPath = join( \DIRECTORY_SEPARATOR, [$appPath, 'data', 'theme.xml'] ) ) )
        {
            $this->task( 'Rebuilding template files', function () use ( $appPath, $devPath, $themeXmlPath, $fs ) {
                $service = new \Sabre\Xml\Service();
                $service = $service->parse( file_get_contents( $themeXmlPath ) );

                foreach ( $service as $xml )
                {
                    // Templates
                    if ( $xml['name'] === '{}template' )
                    {
                        $phtml = sprintf( '<ips:template parameters="%s" />', $xml['attributes']['template_data'] ) . "\n";
                        $phtml .= $xml['value'];

                        $dirPath = join( \DIRECTORY_SEPARATOR, [
                            $devPath,
                            'html',
                            $xml['attributes']['template_location'],
                            $xml['attributes']['template_group'],
                        ] );
                        $filePath = join( \DIRECTORY_SEPARATOR, [
                            $dirPath,
                            $xml['attributes']['template_name'] . '.phtml'
                        ] );

                        @mkdir( $dirPath, 0777, TRUE );
                        file_put_contents( $filePath, $phtml );

                        continue;
                    }

                    // CSS
                    if ( $xml['name'] === '{}css' )
                    {
                        $dirPath = join( \DIRECTORY_SEPARATOR, [
                            $devPath,
                            'css',
                            $xml['attributes']['css_location'],
                        ] );

                        if ( $xml['attributes']['css_path'] !== '.' )
                        {
                            $dirPath = join( \DIRECTORY_SEPARATOR, [
                                $dirPath,
                                $xml['attributes']['css_path']
                            ] );
                        }

                        $filePath = join( \DIRECTORY_SEPARATOR, [
                            $dirPath,
                            $xml['attributes']['css_name']
                        ] );

                        @mkdir( $dirPath, 0777, TRUE );
                        file_put_contents( $filePath, $xml['value'] );

                        continue;
                    }

                    // Resources
                    if ( $xml['name'] === '{}resource' )
                    {
                        $resource = base64_decode( $xml['value'] );

                        $dirPath = join( \DIRECTORY_SEPARATOR, [
                            $devPath,
                            'resources',
                            $xml['attributes']['location'],
                            $xml['attributes']['path']
                        ] );

                        $filePath = join ( \DIRECTORY_SEPARATOR, [
                            $dirPath,
                            $xml['attributes']['name']
                        ] );

                        @mkdir( $dirPath, 077, TRUE );
                        file_put_contents( $filePath, $resource );
                    }
                }
            } );
        }

        /**
         * REBUILD JAVASCRIPT FILES
         */
        if ( $fs->exists( $jsXmlPath = join( \DIRECTORY_SEPARATOR, [ $appPath, 'data', 'javascript.xml' ] ) ) )
        {
            $this->task( 'Rebuilding javascript files', function () use ( $appPath, $devPath, $jsXmlPath, $fs, &$lang, &$jsLang ) {
                $service = new \Sabre\Xml\Service();
                $service = $service->parse( file_get_contents( $jsXmlPath ) );

                foreach ( $service as $xml )
                {
                    $dirPath = join( \DIRECTORY_SEPARATOR, [
                        $devPath,
                        'js',
                        $xml['attributes']['javascript_location'],
                        \str_replace( '/', \DIRECTORY_SEPARATOR, $xml['attributes']['javascript_path'] ),
                    ] );

                    $filePath = join( \DIRECTORY_SEPARATOR, [
                        $dirPath,
                        $xml['attributes']['javascript_name']
                    ] );

                    @mkdir( $dirPath, 0777, TRUE );
                    file_put_contents( $filePath, $xml['value'] );
                }
            } );

            /**
             * TODO: REBUILD E-MAIL TEMPLATES
             */
        }
    }

    /**
     * Checks whether or not the specific app is owned by IPS
     * @return bool
     */
    protected function isInvisionApp()
    {
        // We can't just rely on the author since _someone_ forgot to set the author for Blog
        return in_array( $this->app->directory, ['core', 'forums', 'downloads', 'blog', 'gallery', 'calendar', 'cms', 'nexus'] );
    }

    /**
     * Show whether the application is enabled or disabled
     * @return string
     */
    protected function getToggleOption()
    {
        return $this->app->enabled ? 'Disable' : 'Enable';
    }

    /**
     * Recursively zip a specified directory
     * @example https://stackoverflow.com/questions/1334613/how-to-recursively-zip-a-directory-in-php
     * @param $source
     * @param $destination
     * @return bool
     */
    public static function recursiveZip( $source, $destination )
    {
        $zip = new \ZipArchive();
        $zip->open( $destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

        $source = \str_replace( '\\', '/', \realpath( $source ) );

        if ( \is_dir( $source ) === TRUE )
        {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $source ), \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $files as $file )
            {
                $file = \str_replace( '\\', '/', $file );

                // Ignore "." and ".." folders
                if ( in_array( \substr( $file, \strrpos( $file, '/' ) + 1 ), [ '.', '..' ] ) )
                {
                    continue;
                }

                $file = \realpath( $file );

                if ( \is_dir( $file ) === TRUE )
                {
                    $zip->addEmptyDir( \str_replace( $source . '/', '', $file . '/' ) );
                }
                else
                {
                    if ( is_file( $file ) === TRUE )
                    {
                        $zip->addFromString( \str_replace( $source . '/', '', $file ), \file_get_contents( $file ) );
                    }
                }
            }
        }
        else
        {
            if ( \is_file( $source ) === TRUE )
            {
                $zip->addFromString( \basename( $source ), \file_get_contents( $source ) );
            }
        }

        return $zip->close();
    }
}
