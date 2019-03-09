<?php

namespace App\Providers;

use App\Invision\Invision;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Invision::class, function ($app, $path = null) {
            $path = $path ?: config('invision.path', 'init.php');

            return new Invision($path);
        });
    }
}
