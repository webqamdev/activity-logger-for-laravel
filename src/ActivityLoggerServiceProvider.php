<?php

namespace Webqamdev\ActivityLogger;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\ActivityLogger as SpatieActivityLogger;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity as SpatieLogsActivity;
use Webqamdev\ActivityLogger\Traits\LogsActivity;

class ActivityLoggerServiceProvider extends ServiceProvider
{
    public const CLASS_SUFFIX = '__ActivityLogger';
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

        $this->mergeConfigFrom(__DIR__ . '/../config/activitylogger.php', 'activitylogger');

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

        Event::listen(['eloquent.booting: *'], function ($event, $models) {
            foreach ($models as $model) {
                $this->applyLogsActivityTrait($model);
            }
        });

        $this->app->bind(ActivityLogger::class, \Webqamdev\ActivityLogger\Listeners\ActivityLogger::class);
    }

    /**
     * Dynamically apply the LogsActivity trait to all models.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    protected function applyLogsActivityTrait(Model|string $model): void
    {
        // Ignore activity model and configured models
        if (!$model instanceof Activity && ! in_array(SpatieLogsActivity::class, class_uses_recursive($model))) {
            $this->addLogsActivityToModel(get_class($model));
        }
    }

    protected function addLogsActivityToModel(string $modelClass): void
    {
        $logClass = $modelClass . self::CLASS_SUFFIX;
        if (class_exists($logClass) || Str::endsWith($modelClass, self::CLASS_SUFFIX)) {
            return;
        }

        $reflectionClass = new \ReflectionClass($modelClass);
        eval(sprintf(
            'namespace %s;'
            . 'class %s extends \\%s {'
            . 'use \\%s; '
            . 'public function getMorphClass(): string { return \'%s\'; }'
            . '};',
            $reflectionClass->getNamespaceName(),
            $reflectionClass->getShortName() . self::CLASS_SUFFIX,
            $modelClass,
            LogsActivity::class,
            $modelClass,
        ));

        // Events observed by the default ActivityLogger.
        $events = ['created', 'updating', 'updated', 'deleted'];
        if (collect(class_uses_recursive($modelClass))->contains(SoftDeletes::class)) {
            $events[] = 'restored';
        }

        foreach ($events as $event) {
            $logMethod = 'logActivity' . ucfirst($event) . 'Event';
            $modelClass::{$event}(fn (Model $model) => $logClass::cloneToActivityLogger($model)->{$logMethod}());
        }
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
        $name       = config('activitylogger.channel.name', 'user');
        $path       = config('activitylogger.channel.path', storage_path('logs/activity.log'));
        $days       = config('activitylogger.channel.days', 14);
        $level      = config('activitylogger.channel.level', 'debug');
        $permission = config('activitylogger.channel.permission', 0644);

        // Make logger
        $log = new Logger($name);
        $log->pushHandler(new RotatingFileHandler($path, $days, $level, true, $permission));

        return $log;
    }
}
