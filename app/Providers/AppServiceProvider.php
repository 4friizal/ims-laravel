<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // `artisan serve` only forwards a whitelist of environment variables to
        // the PHP server subprocess that actually handles requests. Without
        // this, the DB_* values supplied by docker-compose are dropped and the
        // app falls back to .env — pointing it at 127.0.0.1 instead of `db`.
        ServeCommand::$passthroughVariables = array_merge(ServeCommand::$passthroughVariables, [
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
