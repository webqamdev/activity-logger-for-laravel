<?php

namespace Webqam\ActivityLogger\Listeners;

use App\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Webqam\ActivityLogger\ActivityLoggerServiceProvider;

class ActivityLogger
{
    /** @var User|null */
    protected $user;

    /**
     * Create the event listener.
     *
     * @param User|null $user
     */
    public function __construct(User $user = null)
    {
        if (! empty($user->id)) {
            $this->user = $user;
        } else {
            $this->user = self::getCurrentUser();
        }
    }

    /**
     * Handle the event.
     *
     * @param string $event
     * @param array  $models
     *
     * @return void
     */
    public function handle(string $event, array $models)
    {
        if (config('activitylogger.enabled', true) === false) {
            return;
        }

        $eventAction = substr($event, 0, strpos($event, ':'));
        $action      = substr($eventAction, strpos($eventAction, '.') + 1);
        /** @var Model $model */
        $model = array_pop($models);
        if ($model instanceof Activity) {
            return;
        }

        // Get only visible dirty values
        $modelHidden  = optional($model)->activity_hidden ?? [];
        $configHidden = config('activitylogger.properties_hidden', ['password']);
        $hidden       = array_merge($modelHidden, $configHidden);
        $dirty        = array_filter($model->getDirty(), function (string $key) use ($hidden) {
            return ! in_array($key, $hidden);
        }, ARRAY_FILTER_USE_KEY);

        self::logDatabase($action, $model, $this->user, $dirty);
        self::logFile($action, $model, $this->user, $dirty);
    }

    public static function logDatabase(string $action, Model $on, Model $by = null, array $with = null)
    {
        if (config('activitylogger.to_database', true) === false) {
            return;
        }

        ActivityLoggerServiceProvider::activity($action)
            ->performedOn($on)
            ->causedBy($by)
            ->withProperties($with)
            ->log(":causer.email(:causer.id) has $action Model");
    }

    public static function logFile(string $action, Model $on, Model $by = null, array $with = null)
    {
        $by          = optional($by);
        $onClass     = get_class($on);
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
