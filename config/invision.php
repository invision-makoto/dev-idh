<?php

$__path = env( 'IPS_PATH', './' );

return [

    /*
    |--------------------------------------------------------------------------
    | Path
    |--------------------------------------------------------------------------
    |
    | This must contain either a full or relative path to your development
    | installation in order for the majority of commands to function correctly.
    |
    */

    'path' => $__path,

    /*
    |--------------------------------------------------------------------------
    | Builds directory
    |--------------------------------------------------------------------------
    |
    | This is the directory builds from the "Build for release" command
    | are stored in.
    |
    */
    'builds_path' => env( 'IPS_BUILDS_PATH', \join( \DIRECTORY_SEPARATOR, [ $__path, 'builds' ] ) ),

    /*
    |--------------------------------------------------------------------------
    | Builds directory
    |--------------------------------------------------------------------------
    |
    | This is the directory builds from the "Build for release" command
    | are stored in.
    |
    */
    'backups_path' => env( 'IPS_BACKUPS_PATH', \join( \DIRECTORY_SEPARATOR, [ $__path, 'backups' ] ) )

];
