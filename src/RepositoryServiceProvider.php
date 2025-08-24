<?php

namespace Litepie\Repository;

use Illuminate\Support\ServiceProvider;
use Litepie\Repository\Console\Commands\MakeRepositoryCommand;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/repository.php', 'repository'
        );
    }

    /**
     * Bootstrap the service provider.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/repository.php' => config_path('repository.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/repository'),
            ], 'stubs');

            $this->commands([
                MakeRepositoryCommand::class,
            ]);
        }
    }
}
