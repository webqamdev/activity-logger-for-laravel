<?php

namespace Webqamdev\ActivityLogger\Listeners;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\EventLogBag;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Webqamdev\ActivityLogger\ActivityLogger as Logger;
use Webqamdev\ActivityLogger\ActivityLoggerServiceProvider;

class ActivityLogger
{
    public const int CACHE_DURATION_IN_DAYS = 1;

    public const string CACHE_KEY_PURGE = 'flag_delete_logs';

    protected readonly ?Authenticatable $user;

    /**
     * Create the event listener.
     */
    public function __construct(?Authenticatable $user = null)
    {
        $this->user = $user ?? self::getCurrentUser();
    }

    public static function getCurrentUser(): ?Authenticatable
    {
        if (Auth::check()) {
            return Auth::user();
        }

        return self::getCurrentBackpackUser();
    }

    public static function getCurrentBackpackUser(): ?Authenticatable
    {
        if (self::isBackpackInstalled() && backpack_auth()->check()) {
            return backpack_user();
        }

        return null;
    }

    public static function isBackpackInstalled(): bool
    {
        return function_exists('backpack_user') && function_exists('backpack_auth');
    }

    public function handle(string $event, array $models): void
    {
        if (config('activitylogger.enabled', true) === false) {
            return;
        }

        $eventAction = substr($event, 0, strpos($event, ':'));
        $action = substr($eventAction, strpos($eventAction, '.') + 1);

        /** @var Model $model */
        $model = array_pop($models);

        if ($model instanceof Activity) {
            return;
        }

        $activityLogger = $this->getActivityLogger($model, $action);

        if (is_null($activityLogger)) {
            return;
        }

        $description = sprintf('%s#%s has been %s', $model::class, $model->getKey(), $action);

        if ($this->user instanceof Authenticatable && $this->user->id) {
            $description = sprintf(
                '%s by %s(%s)',
                $description,
                $this->user->email,
                $this->user->id,
            );
        }

        if (config('activitylogger.to_database', true) === true) {
            $activityLogger->log($description);
        }

        $activityLogger->logFile($description);

        ActivityLogger::purgeDatabase();
    }

    /**
     * @param  Model|LogsActivity|null  $model
     */
    protected function getActivityLogger(?Model $model, string $eventName): ?Logger
    {
        // Get only visible dirty values
        $hidden = array_merge(
            $model?->logAttributesToIgnore ?? [],
            config('activitylogger.properties_hidden', ['password']),
        );
        $dirty = array_filter(
            $model->getDirty(),
            fn (string $key): bool => ! in_array($key, $hidden),
            ARRAY_FILTER_USE_KEY,
        );

        $activityLogger = ActivityLoggerServiceProvider::activity()
            ->performedOn($model)
            ->causedBy($this->user);

        if (method_exists($model, 'getActivitylogOptions')) {
            $model->activitylogOptions = $model->getActivitylogOptions();

            $changes = $model->attributeValuesToBeLogged($eventName);

            // Submitting empty description will cause placeholder replacer to fail.
            if ($model->getDescriptionForEvent($eventName) === '') {
                return null;
            }

            if ($model->isLogEmpty($changes) && ! $model->activitylogOptions->submitEmptyLogs) {
                return null;
            }

            // User can define a custom pipelines to mutate, add or remove from changes
            // each pipe receives the event carrier bag with changes and the model in
            // question every pipe should manipulate new and old attributes.
            $event = app(Pipeline::class)
                ->send(new EventLogBag($eventName, $model, $changes, $model->activitylogOptions))
                ->through($model::$changesPipes)
                ->thenReturn();

            /** @var EventLogBag $event */
            $dirty = $event->changes;

            // Reset log options so the model can be serialized.
            unset($model->activitylogOptions);
        }

        $activityLogger->withProperties($dirty);

        if (method_exists($model, 'tapActivity')) {
            $activityLogger->tap([$model, 'tapActivity'], $eventName);
        }

        return $activityLogger;
    }

    protected static function purgeDatabase(): void
    {
        if (Cache::has(ActivityLogger::CACHE_KEY_PURGE)) {
            return;
        }

        Cache::put(
            key: ActivityLogger::CACHE_KEY_PURGE,
            value: time(),
            ttl: now()->addDays(ActivityLogger::CACHE_DURATION_IN_DAYS),
        );

        if (config('activitylogger.to_database', true)) {
            Activity::query()
                ->where(
                    Activity::CREATED_AT,
                    '<=',
                    now()->subDay(config('activitylogger.days_before_delete_log')),
                )
                ->delete();
        }
    }
}
