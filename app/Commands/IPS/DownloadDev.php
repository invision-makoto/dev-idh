<?php

namespace App\Commands\IPS;

use Behat\Mink\Element\DocumentElement;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Behat\Mink\Driver\GoutteDriver,
    Behat\Mink\Driver\Goutte\Client as GoutteClient;

class DownloadDev extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'ips:download-dev
    {--user= : Your IPS client area username}
    {--pass= : Your IPS client area password}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Download the latest development resources from the IPS marketplace';

    /**
     * Community login URL
     * @var string
     */
    protected static $loginUrl = 'https://invisioncommunity.com/login/';

    /**
     * Developer Tools URL
     * @var string
     */
    protected static $devToolsUrl = 'https://invisioncommunity.com/files/file/7185-developer-tools/';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->option( 'user' ) ?? env( 'IPS_USER' ) ?? $this->ask( 'What is your IPS client area username?' );
        $pass = $this->option( 'pass' ) ?? env( 'IPS_PASS' ) ?? $this->secret( 'What is your IPS client area password?' );

        $mink = new Mink( [ 'community' => new Session( new GoutteDriver( new GoutteClient() ) ) ] );

        // set the default session name
        $mink->setDefaultSessionName( 'community' );
        $session = $mink->getSession();

        $getLoginForm = function( DocumentElement $page )
        {
            return $page->find( 'css', '#ipsLayout_contentWrapper' );
        };

        // Login
        $session->visit( static::$loginUrl );
        $loginForm = $getLoginForm( $session->getPage() );
        $loginForm->find( 'css', 'input#auth' )->setValue( $user );
        $loginForm->find( 'css', 'input#password' )->setValue( $pass );
        $loginForm->findButton( '_processLogin' )->submit();

        // Any errors?
        if ( $session->getCurrentUrl() === static::$loginUrl )
        {
            $loginForm = $getLoginForm( $session->getPage() );

            $error = $loginForm->find( 'css', '.ipsMessage_error' );
            $error = $error->getText() ?: 'An unknown error occurred';
            $this->error( $error );
            exit( 1 );
        }

        // Still here? Great! Let's visit the download page
        $session->visit( static::$devToolsUrl );
        $downloadPage = $session->getPage();
        $downloadPage->findLink( 'Download this file' )->click();

        file_put_contents( 'IPS Developer Tools.zip', $session->getPage()->getContent() );
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
