<?php

namespace Sleuren;

use Monolog\Logger;
use Sleuren\Commands\TestCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Sleuren\Recorders\JobRecorder\JobRecorder;
use Sleuren\Recorders\LogRecorder\LogRecorder;
use Sleuren\Recorders\DumpRecorder\DumpRecorder;
use Sleuren\Recorders\QueryRecorder\QueryRecorder;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        // Publish configuration file
        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/../config/sleuren.php' => config_path('sleuren.php'),
            ]);
        }

        // Register views
        $this->app['view']->addNamespace('sleuren', __DIR__ . '/../resources/views');

        // Register facade
        if (class_exists(\Illuminate\Foundation\AliasLoader::class)) {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Sleuren', 'Sleuren\Facade');
        }

        // Register commands
        $this->commands([
            TestCommand::class,
        ]);

        // Map any routes
        $this->mapSleurenApiRoutes();
        $this->startRecorders();

        // Create an alias to the js-client.blade.php include
        Blade::include('sleuren::js-client', 'Sleuren');
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sleuren.php', 'sleuren');
        $this->registerRecorders();

        $this->app->singleton('sleuren', function ($app) {
            return new Sleuren(new \Sleuren\Http\Client(
                config('sleuren.project_key', 'project_key')
            ));
        });

        if ($this->app['log'] instanceof \Illuminate\Log\LogManager) {
            $this->app['log']->extend('sleuren', function ($app, $config) {
                $handler = new \Sleuren\Logger\SleurenHandler(
                    $app['sleuren']
                );
                return new Logger('sleuren', [$handler]);
            });
        }

    }

    protected function mapSleurenApiRoutes()
    {
        Route::group(
            [
                'namespace' => '\Sleuren\Http\Controllers',
                'prefix' => 'sleuren-api'
            ],
            function ($router) {
                require __DIR__ . '/../routes/api.php';
            }
        );
    }

    protected function startRecorders(): void
    {
        foreach ($this->app->config['sleuren.recorders'] ?? [] as $recorder) {
            $this->app->make($recorder)->start();
        }
    }

    protected function registerRecorders(): void
    {
        $this->app->singleton(DumpRecorder::class);

        $this->app->singleton(LogRecorder::class, function (Application $app): LogRecorder {
            return new LogRecorder(
                $app,
                50,
            );
        });

        $this->app->singleton(
            QueryRecorder::class,
            function (Application $app): QueryRecorder {
                return new QueryRecorder(
                    $app,
                    true,
                    200
                );
            }
        );

        $this->app->singleton(JobRecorder::class, function (Application $app): JobRecorder {
            return new JobRecorder(
                $app,
                50
            );
        });
    }
}
