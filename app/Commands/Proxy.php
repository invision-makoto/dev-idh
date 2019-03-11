<?php

namespace App\Commands;

use App\Invision\Schema;
use Dariuszp\CliProgressBar;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;

class Proxy extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'proxy {--skip= : Comma separated values of files to skip (refer to documentation)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = '(Re)generates all proxy classes for the application';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $skips = array_map( 'trim', explode( ',', $this->option( 'skip' ) ) );

        $fs = new Filesystem();
        $proxyPath = config( 'invision.path' ) . \DIRECTORY_SEPARATOR . 'proxy_classes';

        // Check for any existing files
        if ( $fs->exists( $proxyPath ) )
        {
            $this->task( 'Clearing out old proxy class files', function () use ( $fs, $proxyPath ) {
                $fs->remove( $proxyPath );
            } );
        }
        $fs->mkdir( $proxyPath );

        $excluded = array_merge( [ 'proxy_classes', 'datastore' ], $skips );
        $filter = function ( $file, $key, $iterator ) use ( $excluded ) {
            if ( $iterator->hasChildren() && !in_array( $file->getFilename(), $excluded ) )
            {
                return TRUE;
            }

            return $file->isFile();
        };

        $dirIterator = new \RecursiveDirectoryIterator(
            config( 'invision.path' ),
            \RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator($dirIterator, $filter),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        // Get a list of files to iterate over
        $scriptsToParse = [];
        $schemasToParse = [];

        $this->task( 'Scanning for PHP scripts', function () use ( $iterator, &$scriptsToParse ) {
            $scriptsIterator = new \RegexIterator( $iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH );
            foreach ( $scriptsIterator as $file )
            {
                Log::debug( "Script: {$file[0]}" );
                $scriptsToParse[] = $file[0];
            }
        } );

        $this->task( 'Scanning for database schema files', function () use ( $iterator, &$schemasToParse ) {
            $schemaIterator = new \RegexIterator( $iterator, '/^.*schema\.json$/i', \RecursiveRegexIterator::GET_MATCH );
            foreach ( $schemaIterator as $file )
            {
                Log::debug( "Schema: {$file[0]}" );
                $schemasToParse[] = $file[0];
            }
        } );

        $count = count( $scriptsToParse ) + count( $schemasToParse );
        Log::info( count( $scriptsToParse ) . ' script files matches' );
        Log::info( count( $schemasToParse ) . ' schema files matches' );

        // Start our progress bar and disable the console cursor
        $this->getOutput()->write("\033[?25l", true);
//        $progress = new ProgressBar($output, $count);
        $bar = new CliProgressBar( $count );
        $bar->display();
        $bar->setColorToRed();

        $itsYellow = FALSE;
        $updateBarColor = function ( CliProgressBar $bar ) use ( &$itsYellow )
        {
            if ( $itsYellow )
            {
                return;
            }

            if ( $bar->getCurrentstep() >= ( $bar->getSteps() / 2 ) )
            {
                $bar->setColorToYellow();
                $itsYellow = TRUE;
            }
        };

//        $progress->setFormat(" %namespace%\n %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%");

        $i = 0;
        foreach ( $schemasToParse as $file )
        {
            $this->parseJsonSchema( $file, $bar );
            $i++;
            $bar->progress();
            $updateBarColor( $bar );
        }

        foreach ( $scriptsToParse as $file )
        {
            $filePath = $file;
            $this->parsePhpGeneric( $filePath, $bar );

            $i++;
            $bar->progress();
            $updateBarColor( $bar );
        }

        // Finish and re-enable the cursor
        $bar->setColorToGreen();
        $bar->end();
        $this->getOutput()->write("\033[?25h", true);
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

    protected function parsePhpGeneric($filePath, CliProgressBar $progress)
    {
        $handle = @fopen( $filePath, "r" );
        if ($handle)
        {
            $namespace = '';
            $table = NULL;
            $prefix = NULL;
            $content = "<?php\n\n";
            $classContent = '';

            while ( !feof( $handle ) )
            {
                $line = \fgets( $handle, 4096 );
                $matches = [];

                // Get the namespace
                preg_match( '/^namespace(.+?)([^\;]+)/', $line, $matched );
                if ( isset( $matched[0] ) )
                {
                    $namespace = $matched[0];
                    $content .= $namespace . ";\n\n";
                }

                # Database prefix
                if ( preg_match(
                    '/^\s*(?:final\s+)?(?:public|protected|private)\s+static\s+\$databasePrefix\s+=\s+[\'"](?P<prefix>[a-zA-Z_]+)[\'"];\s*$/i',
                    $line, $matches
                ) )
                {
                    $prefix = $matches['prefix'];
                }

                # Database table
                if ( preg_match(
                    '/^\s*(?:final\s+)?(?:public|protected|private)\s+static\s+\$databaseTable\s+=\s+[\'"](?P<table>[a-zA-Z_]+)[\'"];\s*$/i',
                    $line, $matches
                ) )
                {
                    $table = $matches['table'];

                }

                # Class _ extends _
                if (preg_match('#^(\s*)((?:(?:abstract|final|static)\s+)*)class\s+([-a-zA-Z0-9_]+)(?:\s+extends\s+([-a-zA-Z0-9_]+))?(?:\s+implements\s+([-a-zA-Z0-9_,\s]+))?#', $line, $matches) )
                {
                    if ( substr( $matches[3], 0, 1 ) === '_' )
                    {
                        $append = ltrim( $matches[3], '\\' );

                        $m = ltrim( $matches[3], '\\' );
                        $m = str_replace( '_', '', $m );
                        $filename = mb_strtolower( $m ) . '.php';

                        $classContent .= $matches[2] . 'class ' . $m . ' extends ' . $append . '{}' . "\n";

//                        $progress->setMessage( $namespace, 'namespace' );
                    }
                }
            }

            // Parse database properties
            if ( $table )
            {
                if ( isset( $this->_schemas[ $table ] ) )
                {
                    Log::info( "Table matched: {$table}" );

                    $columns = $this->_schemas[ $table ]->getColumns();
                    $content .= "/**\n";
                    foreach ( $columns as $column )
                    {
                        // Strip prefixes?
                        $name = $column['name'];
                        if ( $prefix )
                        {
                            if ( substr( $name, 0, strlen( $prefix ) ) == $prefix )
                            {
                                $name = substr( $name, strlen( $prefix ) );
                            }
                        }

                        $content .= ' * ';
                        $content .= "@property {$column['type']}";
                        if ($column['nullable'])
                        {
                            $content .= '|NULL';
                        }
                        $content .= " \${$name} {$column['comment']}\n";
                    }
                    $content .= " */\n";
                }
            }

            // Write output (TODO: This is hideously disorganized)
            $fs = new Filesystem();
            if ( !empty( $filename ) )
            {
                $filePath = join( \DIRECTORY_SEPARATOR, [ config( 'invision.path' ), 'proxy_classes', $filename ] );

                if ( $fs->exists( $filePath ) )
                {
                    file_put_contents( $filePath, $content . $classContent );
                }
                else
                {
                    $alt = str_replace( [ "\\", " ", ";" ], "_", $namespace );
                    $filename = $alt . "_" . $filename;
                    $filePath = join( \DIRECTORY_SEPARATOR, [ config( 'invision.path' ), 'proxy_classes', $filename ] );

                    file_put_contents( $filePath, $content . $classContent );
                }
            }

            fclose( $handle );
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    protected function parseJsonSchema( $filePath, $progress )
    {
        $json = json_decode( file_get_contents( $filePath ) );
        if ( empty( $json ) )
        {
            Log::warning( "Unable to parse json schema: {$filePath}" );
            return FALSE;
        }

        foreach ( $json as $table )
        {
//            $progress->setMessage($table->name, 'namespace');

            if ( !$table->name or empty( $table->columns ) )
            {
                continue;
            }

            $this->_schemas[ $table->name ] = new Schema( $table );
        }
    }
}
