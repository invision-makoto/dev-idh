<?php

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

    'path' => env( 'IPS_PATH', './' ),

    /*
    |--------------------------------------------------------------------------
    | Builds directory
    |--------------------------------------------------------------------------
    |
    | This is the directory builds from the "Build for release" command
    | are stored in.
    |
    */
    'builds_path' => env( 'IPS_BUILDS_PATH', './builds/' ),

    /*
    |--------------------------------------------------------------------------
    | Builds directory
    |--------------------------------------------------------------------------
    |
    | This is the directory builds from the "Build for release" command
    | are stored in.
    |
    */
    'backups_path' => env( 'IPS_BACKUPS_PATH', './backups/' )

];
