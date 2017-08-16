<?php

namespace CatLab\Accounts\Client;

use Illuminate\Support\ServiceProvider;

/**
 * Class CatLabAccountsServiceProvider
 * @package CatLab\Accounts\Client
 */
class CatLabAccountsServiceProvider extends ServiceProvider
{

    public function boot() {
        $this->publishes([
            __DIR__.'/../config/services.php' => config_path('services.php')
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations')
        ], 'migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // TODO: Implement register() method.
    }
}