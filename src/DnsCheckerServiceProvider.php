<?php

namespace Alyakin\DnsChecker;

use Alyakin\DnsChecker\Contracts\DnsLookup;
use Illuminate\Support\ServiceProvider;

class DnsCheckerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dns-checker.php', 'dns-checker');

        $this->app->singleton(DnsLookupService::class, function ($app) {
            return new DnsLookupService(config('dns-checker'));
        });

        $this->app->alias(DnsLookupService::class, 'dns-checker');
        $this->app->singleton(DnsLookup::class, fn ($app) => $app->make(DnsLookupService::class));
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Alyakin\DnsChecker\Commands\DnsCheckCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/dns-checker.php' => config_path('dns-checker.php'),
        ], 'dns-checker-config');
    }
}
