<?php

namespace Webqamdev\ActivityLogger\Listeners;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Webqamdev\ActivityLogger\ActivityLoggerServiceProvider;

class ActivityLogger
{
    const int CACHE_DURATION_IN_DAYS = 1;
    const string CACHE_KEY_PURGE = 'flag_delete_logs';

    protected ?Authenticatable $user;
    protected bool $toDatabase;

    /**
     * Create the event listener.
     *
     */
    public function __construct(?Authenticatable $user = null)
    {
        $this->user = $user ?? self::getCurrentUser();
    }

    public function handle(string $event, array $models): void
    {
        if (false === config('activitylogger.enabled', true)) {
            return;
        }

        $eventAction = substr($event, 0, strpos($event, ':'));
        $action = substr($eventAction, strpos($eventAction, '.') + 1);

        /** @var Model $model */
        $model = array_pop($models);
        if ($model instanceof Activity) {
            return;
        }

        // Get only visible dirty values
        $modelHidden = optional($model)->logAttributesToIgnore ?? [];
        $configHidden = config('activitylogger.properties_hidden', ['password']);
        $hidden = array_merge($modelHidden, $configHidden);
        $dirty = array_filter($model->getDirty(), fn(string $key) => !in_array($key, $hidden), ARRAY_FILTER_USE_KEY);

        self::logDatabase($action, $model, $this->user, $dirty);
        self::logFile($action, $model, $this->user, $dirty);

        ActivityLogger::purgeDatabase();
    }

    private static function purgeDatabase(): void
    {
        if (!Cache::has(ActivityLogger::CACHE_KEY_PURGE)) {
            return;
        }

        Cache::put(ActivityLogger::CACHE_KEY_PURGE, time(), now()->addDays(ActivityLogger::CACHE_DURATION_IN_DAYS));

        if (config('activitylogger.to_database', true)) {
            Activity::query()
                ->where('created_at', '<=', now()->subDay(config('activitylogger.days_before_delete_log')))
                ->delete();
        }
    }

    public static function logDatabase(string $action, Model $on, ?Authenticatable $by = null, ?array $with = null)
    {
        if (config('activitylogger.to_database', true) === false || !$by instanceof Model) {
            return;
        }

        ActivityLoggerServiceProvider::activity($action)
            ->performedOn($on)
            ->causedBy($by)
            ->withProperties($with)
            ->log(":causer.email(:causer.id) has $action Model");
    }

    public static function logFile(string $action, Model $on, ?Authenticatable $by = null, ?array $with = null)
    {
        $by = optional($by);
        $onClass = get_class($on);
        $description = "{$by->email}({$by->id}) has $action Model {$onClass}#{$on->id}";

        ActivityLoggerServiceProvider::logger()
            ->info($description, $with);
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
}
