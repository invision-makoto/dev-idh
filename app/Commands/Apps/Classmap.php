<?php

namespace App\Commands\Apps;

use App\Invision\Invision;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class Classmap extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'apps:classmap
                            {application : The application directory (required)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generate a map of error codes for classes in an IPS application';

    /**
     * @var array
     */
    protected $autoIncrement = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var Invision $ips */
        $ips = app( Invision::class );
        $apps = \IPS\Application::applications();

        // Make sure the application exists
        $appdir = \strtolower( $this->argument( 'application' ) );
        if ( !isset( $apps[ $appdir ] ) )
        {
            $this->error( "Application directory {$appdir} not found. Please check your spelling and try again." );
            return;
        }

        $classMapFilename = 'class_ids';

        /**
         * Language strings
         */
        $lang = [
            'type'      => 'type',
            'classname' => 'classname',
            'namespace' => 'namespace',
            'id'        => 'id',
            'path'      => 'path',

            'key'       => 'Key',
            'value'     => 'Value',
        ];

        /**
         * Maximum ID ranges
         */
        $idRanges = [
            'sources'       => [ 100, 199 ],
            'modules'       => [ 200, 299 ],
            'extensions'    => [ 300, 399 ],
            'hooks'         => [ 400, 499 ],
            'widgets'       => [ 500, 599 ],
            'tasks'         => [ 600, 699 ],
            'interface'     => [ 700, 799 ],
            'setup'         => [ 800, 899 ],
            'misc'          => [ 900, 999 ]
        ];

        // Set up our paths and file handler
        $scriptPath     = config( 'invision.path' ) . \DIRECTORY_SEPARATOR . 'applications' . \DIRECTORY_SEPARATOR . $appdir;
        $classMapPath   = \join( \DIRECTORY_SEPARATOR, [ $scriptPath, 'data', $classMapFilename ] );
        \touch($classMapPath . '.txt');  // Make sure the file exists
        $classMap       = \file( $classMapPath . '.txt', \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES );

        $maps = [];
        $mappedPaths = [];
        $curType = NULL;

        $index = 0;
        foreach ( $classMap as $line )
        {
            // Type definition?
            if ( $def = $this->definition( $line, strtoupper( $lang['type'] ) ) )
            {
                $curType = strtolower( $def );
                if ( !isset( $maps[ $curType ] ) )
                {
                    $maps[ $curType ][ $index ] = [];
                }
            }

            // Classname definition?
            if ( $def = $this->definition( $line, strtoupper( $lang['classname'] ) ) )
            {
                $maps[ $curType ][ $index ]['classname'] = $def;
            }

            // Namespace definition?
            if ( $def = $this->definition( $line, strtoupper( $lang['namespace'] ) ) )
            {
                $maps[ $curType ][ $index ]['namespace'] = $def;
            }

            // ID definition?
            if ( $def = $this->definition( $line, strtoupper( $lang['id'] ) ) )
            {
                $suffix = NULL;
                if ( !is_numeric( $def ) )
                {
                    $suffix = substr( $def, -1 );
                    $def = substr( $def, 0, -1 );
                    $suffix = ord( $suffix );
                }

                $maps[ $curType ][ $index ]['id'] = [ $def, $suffix ];
            }

            // Path definition?
            if ( $def = $this->definition( $line, strtoupper( $lang['path'] ) ) )
            {
                $maps[ $curType ][ $index ]['path'] = $def;
                $mappedPaths[] = $def;
                ++$index;
            }
        }

        // Get a list of files
        $types      = [ 'sources', 'modules', 'extensions', 'hooks', 'widgets', 'tasks', 'interface', 'setup', 'misc' ];
        $filePaths  = [];
        foreach ( $types as $type )
        {
            // Ignore misc
            if ( $type == 'misc' )
                continue;

            $path = join( \DIRECTORY_SEPARATOR, [ $scriptPath, $type ] );

            // Skip it if the directory does not exist
            try
            {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ),
                    \RecursiveIteratorIterator::SELF_FIRST,
                    \RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
                );
            }
            catch ( \UnexpectedValueException $e )
            {
                continue;
            }

            $filePaths[ $type ] = [];
            foreach ( $iter as $path => $file )
            {
                if ( $file->isFile() and ($file->getExtension() == 'php') )
                {
                    $filePaths[ $type ][] = $path;
                }
            }
        }

        // Set our starting point for auto-incrementing ID's
        foreach ( $types as $type )
        {
            // Ignore misc
            if ( $type == 'misc' )
                continue;

            [ $min, $max ]      = $idRanges[ $type ];
            $this->autoIncrement[ $type ] = [ $min, NULL ];

            if ( $maps and !empty( $maps[ $type ] ) )
            {
                foreach ( $maps[ $type ] as $map )
                {
                    // ID Suffix
                    if ( $map['id'][1] )
                    {
                        if ( $map['id'][1] > (int) $this->autoIncrement[ $type ][1] )
                        {
                            $this->autoIncrement[ $type ] = $map['id'];
                        }
                    }

                    // ID Number
                    if ( $map['id'][0] > (int) $this->autoIncrement[ $type ][0] )
                    {
                        $this->autoIncrement[ $type ] = $map['id'];
                    }
                }
            }
        }

        // Iterate over our files
        foreach ( $types as $type )
        {
            // Ignore misc
            if ( $type == 'misc' )
                continue;

            // Skip empty types
            if ( empty( $filePaths[ $type ] ) )
                continue;

            foreach ( $filePaths[ $type ] as $fp )
            {
                $lines = file( $fp, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES );

                // Attempt to match our ClassName and Namespace
                $className = NULL;
                $namespace = NULL;
                foreach ( $lines as $line )
                {
                    // All done?
                    if ( $className and $namespace )
                        break;

                    // Namespace?
                    if ( preg_match( '/namespace\s+([^;]+);/', $line, $namespaceMatch ) )
                    {
                        $namespace = $namespaceMatch[1];
                        continue;
                    }

                    // Class name?
                    $preg = in_array( $type, [ 'hooks', 'interface', 'setup' ] )
                        ? '/class ([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*).*/'
                        :'/class _([a-zA-Z0-9_\x7f-\xff]*).*/';

                    if ( preg_match( $preg, $line, $classNameMatch ) )
                    {
                        $className = $classNameMatch[1];
                        continue;
                    }
                }

                if ( $className and $namespace )
                {
                    $typePath = explode( $type . \DIRECTORY_SEPARATOR, $fp );
                    $path = array_pop( $typePath );

                    if ( in_array( $path, $mappedPaths ) )
                    {
                        continue;
                    }

                    $mappedPaths[] = $fp;
                    $idSet = $this->popId( $type );
                    $maps[ $type ][] = [
                        'classname' => $className,
                        'namespace' => $namespace,
                        'id'        => $idSet,
                        'path'      => $path
                    ];

                    $id = (string) $idSet[0];
                    if ( $idSet[1] )
                    {
                        $id = $id . chr( $idSet[1] );
                    }
                    $this->info( "ID {$id} assigned to class {$namespace}\\{$className}" );
                }
            }
        }

        // Generate the text document
        $classMap = fopen( $classMapPath . '.txt', 'w+' );
        foreach ( $maps as $type => $map )
        {
            foreach ( $map as $subMap )
            {
                $id = (string) $subMap['id'][0];
                if ( $subMap['id'][1] )
                {
                    $id = $id . chr( $id[1] );
                }

                fwrite( $classMap, strtoupper( $lang['type'] ) . ': ' . ucfirst( $type ) );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang[ 'classname' ] ) . ': ' . $subMap['classname'] );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang[ 'namespace' ] ) . ': ' . $subMap['namespace'] );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang[ 'id' ] ) . ': ' . $id );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang[ 'path' ] ) . ': ' . $subMap['path'] );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, PHP_EOL );
            }
        }
        fclose( $classMap );

        // Generate a markdown document
        $classMap = fopen( $classMapPath . '.md', 'w+' );
        foreach ( $maps as $type => $map )
        {
            fwrite( $classMap, '# ' . ucfirst( $type ) );
            fwrite( $classMap, PHP_EOL );

            foreach ( $map as $subMap )
            {
                $id = (string) $subMap['id'][0];
                if ( $subMap['id'][1] )
                {
                    $id = $id . chr( $id[1] );
                }

                fwrite( $classMap, "{$lang['key']} | {$lang['value']}" );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap,
                    str_repeat( '-', strlen( $lang['key'] ) ) . ' | ' .
                    str_repeat( '-', strlen( $lang['value'] ) )
                );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang['type'] ) . ' | ' . ucfirst( $type ) );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang[ 'classname' ] ) . ' | ' . $subMap['classname'] );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang[ 'namespace' ] ) . ' | ' . $subMap['namespace'] );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang[ 'id' ] ) . ' | ' . $id );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, strtoupper( $lang[ 'path' ] ) . ' | ' . $subMap['path'] );
                fwrite( $classMap, PHP_EOL );

                fwrite( $classMap, PHP_EOL );
            }
        }
        fclose( $classMap );
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
     * Check for a definition match, and return the value if successful
     *
     * @param   string  $line   The line being read
     * @param   string  $key    The definition being matched
     * @return  bool|string FALSE if no match, otherwise the actual definition
     */
    public function definition( $line, $key )
    {
        $len = strlen( $key );
        if ( substr( $line, 0, $len ) == $key )
        {
            return substr( $line, $len + 2 );
        }

        return FALSE;
    }

    /**
     * Get an ID number and increment
     *
     * @param   string  $type   The class type
     * @return  array
     */
    public function popId( $type )
    {
        $idRanges = [
            'sources'       => [ 100, 199 ],
            'modules'       => [ 200, 299 ],
            'extensions'    => [ 300, 399 ],
            'hooks'         => [ 400, 499 ],
            'widgets'       => [ 500, 599 ],
            'tasks'         => [ 600, 699 ],
            'interface'     => [ 700, 799 ],
            'setup'         => [ 800, 899 ],
            'misc'          => [ 900, 999 ]
        ];

        [ $min, $max ]   = $idRanges[ $type ];
        [ $id, $suffix ] = $this->autoIncrement[ $type ];
        if ( $id >= $max )
        {
            $id = $min;
            $suffix = $suffix ? ++$suffix : '96';
        }
        else
        {
            ++$id;
        }
        $this->autoIncrement[ $type ] = [ $id, $suffix ];
        return $this->autoIncrement[ $type ];
    }

}
