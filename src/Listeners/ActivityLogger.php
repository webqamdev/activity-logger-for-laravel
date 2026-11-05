<?php

namespace Webqamdev\ActivityLogger\Listeners;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
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

        if (method_exists($model, 'getActivitylogOptions')) {
            /** @var LogOptions $logOptions */
            $logOptions = $model->getActivitylogOptions();

            if (! empty($logOptions->descriptionForEvent)) {
                $descriptionForEvent = ($logOptions->descriptionForEvent)($action);
            }

            if (! empty($descriptionForEvent)) {
                $description = $descriptionForEvent;
            }
        }

        if ($this->user instanceof Authenticatable && $this->user->id) {
            $description = sprintf(
                '%s by %s(%s)',
                $description,
                $this->user->email,
                $this->user->id,
            );
        }

        $activityLogger->log($description);

        ActivityLogger::purgeDatabase();
    }

    protected function getActivityLogger(?Model $model, string $eventName): ?Logger
    {
        /** @var Model|LogsActivity $model */
        $activityLogger = ActivityLoggerServiceProvider::activity()
            ->performedOn($model)
            ->causedBy($this->user)
            ->setEvent($eventName);

        if (method_exists($model, 'getActivitylogOptions')) {
            $changes = $this->buildModelChanges($model, $eventName);

            if ($changes === null) {
                return null;
            }

            $activityLogger
                ->useLog($model->getLogNameToUse())
                ->withChanges($changes);
        } else {
            $hidden = array_merge(
                $model?->logAttributesToIgnore ?? [],
                config('activitylogger.properties_hidden', ['password']),
            );
            $dirty = array_filter(
                $model->getDirty(),
                fn (string $key): bool => ! in_array($key, $hidden),
                ARRAY_FILTER_USE_KEY,
            );

            $activityLogger->withChanges(['attributes' => $dirty]);
        }

        return $activityLogger;
    }

    /**
     * Apply the model's LogOptions like Spatie does, then build the changes.
     *
     * @param  Model&LogsActivity  $model
     * @return array<string, mixed>|null null when the event must not be logged
     */
    protected function buildModelChanges(Model $model, string $eventName): ?array
    {
        /** @var Closure(string): ?array<string, mixed> $buildChanges */
        $buildChanges = Closure::bind(
            function (string $event): ?array {
                $this->activitylogOptions = $this->getActivitylogOptions();

                // Package trait disables shouldLogEvent() and exposes Spatie's one under an alias.
                $shouldLog = method_exists($this, 'shouldLogActivityEvent')
                    ? $this->shouldLogActivityEvent($event)
                    : $this->shouldLogEvent($event);

                if (! $shouldLog) {
                    return null;
                }

                $changes = $this->buildChanges($event);

                return $this->shouldSkipEmptyLog($changes) ? null : $changes;
            },
            $model,
            $model,
        );

        return $buildChanges($eventName);
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
