<?php

namespace Webqamdev\ActivityLogger;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Webqamdev\ActivityLogger\Listeners\ActivityLogger as ActivityLoggerListener;

class ActivityLoggerServiceProvider extends ServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
        $this->publishes([
            __DIR__.'/../config/activitylogger.php' => config_path('activitylogger.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../config/activitylogger.php', 'activitylogger');

        $this->app->bind(Authenticatable::class, config('activitylogger.user_model'));
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        parent::boot();

        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *', 'eloquent.restored: *'],
            ActivityLoggerListener::class,
        );
    }

    /**
     * Return activity() helper's result with package config.
     */
    public static function activity(): ActivityLogger
    {
        $activity = app()->make(ActivityLogger::class);
        $config = config('activitylogger.enabled', true);

        if ($config === true) {
            $activity->enableLogging();
        } elseif ($config === false) {
            $activity->disableLogging();
        } // Else use default config

        return $activity;
    }
}
