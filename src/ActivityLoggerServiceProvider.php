<?php

namespace Webqam\ActivityLogger;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Spatie\Activitylog\ActivityLogger as SpatieActivityLogger;
use Webqam\ActivityLogger\Listeners\ActivityLogger;

class ActivityLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->publishes([
            __DIR__ . '/../config/activitylogger.php' => config_path('activitylogger.php'),
        ], 'config');

        $this->app->bind(Authenticatable::class, config('activitylogger.user_model'));
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        Event::listen(['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'], ActivityLogger::class);
    }

    /**
     * Return activity() helper's result with package config.
     *
     * @param string|null $logName
     *
     * @return SpatieActivityLogger
     */
    public static function activity(string $logName = null): SpatieActivityLogger
    {
        $activity = activity($logName);
        $config   = config('activitylogger.enabled', true);

        if ($config === true) {
            $activity->enableLogging();
        } elseif ($config === false) {
            $activity->disableLogging();
        } // Else use default config

        return $activity;
    }

    /**
     * Return Logger to use. Replace Log Facade.
     *
     * @return Logger
     */
    public static function logger(): Logger
    {
        // Get config
        $name  = config('activitylogger.channel.name', 'user');
        $path  = config('activitylogger.channel.path', storage_path('logs/activity.log'));
        $days  = config('activitylogger.channel.days', 14);
        $level = config('activitylogger.channel.level', 'debug');

        // Make logger
        $log = new Logger($name);
        $log->pushHandler(new RotatingFileHandler($path, $days, $level));

        return $log;
    }
}
