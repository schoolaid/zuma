<?php

namespace SchoolAid\Zuma;

use Illuminate\Support\ServiceProvider;

class ZumaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/zuma.php', 'zuma'
        );

        $this->app->singleton('zuma', function ($app) {
            return new Client(
                config('zuma.base_url'),
                config('zuma.username'),
                config('zuma.password')
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/zuma.php' => config_path('zuma.php'),
            ], 'zuma-config');
        }
    }
}