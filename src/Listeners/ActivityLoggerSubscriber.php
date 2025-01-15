<?php

namespace Webqamdev\ActivityLogger\Listeners;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Webqamdev\ActivityLogger\ActivityLoggerServiceProvider;

class ActivityLoggerSubscriber
{
    const CACHE_DURATION_IN_DAYS = 1;

    const CACHE_KEY_PURGE = 'flag_delete_logs';

    protected array $oldAttributes = [];

    public function handleUpdating(string $event, Model $model): void
    {
        if ($model instanceof Activity) {
            return;
        }

        $this->oldAttributes[$this->getModelOldAttributesKey($model)] = $this->logChanges($model);
    }

    public function handleLogSave(string $event, Model $model): void
    {
        if ($model instanceof Activity) {
            return;
        }

        $changes = $this->attributeValuesToBeLogged($event, $model);

        $causer = self::getCurrentUser();

        $this->logToDatabase($event, $model, $causer, $changes);
        $this->logToFile($event, $model, $causer, $changes);
        $this->purgeDatabase();
    }

    public function attributesToBeLogged(Model $model): array
    {
        $attributes = array_keys($model->getAttributes());

        $attributesToIgnore = array_merge(
            config('activitylogger.properties_hidden', []),
            optional($model)->logAttributesToIgnore ?? [],
        );

        if (! empty($attributesToIgnore)) {
            // Filter out the attributes defined in ignoredAttributes out of the local array
            $attributes = array_diff($attributes, $attributesToIgnore);
        }

        return $attributes;
    }

    public function logChanges(Model $model): array
    {
        $changes = [];
        $attributes = $this->attributesToBeLogged($model);

        foreach ($attributes as $attribute) {
            if (Str::contains($attribute, '.')) {
                $changes += self::getRelatedModelAttributeValue($model, $attribute);

                continue;
            }

            if (Str::contains($attribute, '->')) {
                Arr::set(
                    $changes,
                    str_replace('->', '.', $attribute),
                    static::getModelAttributeJsonValue($model, $attribute)
                );

                continue;
            }

            $changes[$attribute] = $model->getAttributes()[$attribute] ?? null;
        }

        return $changes;
    }

    /**
     * Determines values that will be logged based on the difference.
     **/
    public function attributeValuesToBeLogged(string $processingEvent, Model $model): array
    {
        // no loggable attributes, no values to be logged!
        if (! count($this->attributesToBeLogged($model))) {
            return [];
        }

        $properties['attributes'] = static::logChanges(
            $model->exists
                ? $model->fresh() ?? $model
                : $model
        );

        if ($processingEvent === 'updated') {
            // Get the old attributes key.
            $oldAttributesKey = $this->getModelOldAttributesKey($model);

            // Fill the attributes with null values.
            $nullProperties = array_fill_keys(array_keys($properties['attributes']), null);

            // Populate the old key with keys from database and from old attributes.
            $properties['old'] = array_merge($nullProperties, $this->oldAttributes[$oldAttributesKey]);

            // Fail-safe.
            $this->oldAttributes[$oldAttributesKey] = [];
        }

        if ($processingEvent === 'deleted') {
            $properties['old'] = $properties['attributes'];
            unset($properties['attributes']);
        }

        return $properties;
    }

    protected static function getRelatedModelAttributeValue(Model $model, string $attribute): array
    {
        $relatedModelNames = explode('.', $attribute);
        $relatedAttribute = array_pop($relatedModelNames);

        $attributeName = [];
        $relatedModel = $model;

        do {
            $attributeName[] = $relatedModelName = static::getRelatedModelRelationName($relatedModel, array_shift($relatedModelNames));

            $relatedModel = $relatedModel->$relatedModelName ?? $relatedModel->$relatedModelName();
        } while (! empty($relatedModelNames));

        $attributeName[] = $relatedAttribute;

        return [implode('.', $attributeName) => $relatedModel->$relatedAttribute ?? null];
    }

    protected static function getRelatedModelRelationName(Model $model, string $relation): string
    {
        return Arr::first([
            $relation,
            Str::snake($relation),
            Str::camel($relation),
        ], function (string $method) use ($model): bool {
            return method_exists($model, $method);
        }, $relation);
    }

    protected static function getModelAttributeJsonValue(Model $model, string $attribute): mixed
    {
        $path = explode('->', $attribute);
        $modelAttribute = array_shift($path);
        $modelAttribute = collect($model->getAttribute($modelAttribute));

        return data_get($modelAttribute, implode('.', $path));
    }

    protected function getModelOldAttributesKey(Model $model): string
    {
        return sprintf('%s->%s', get_class($model), $model->getKey());
    }

    protected function purgeDatabase(): void
    {
        if (Cache::has(self::CACHE_KEY_PURGE) === false) {
            Cache::put(self::CACHE_KEY_PURGE, time(), now()->addDays(self::CACHE_DURATION_IN_DAYS));
            Activity::query()
                ->where(
                    'created_at',
                    '<=',
                    now()->subDays(config('activitylogger.days_before_delete_log', 90)),
                )
                ->delete();
        }
    }

    public function logToDatabase(string $event, Model $on, ?Authenticatable $causer = null, ?array $with = null): void
    {
        if (config('activitylogger.to_database', true) === false || ! $causer instanceof Model) {
            return;
        }

        ActivityLoggerServiceProvider::activity(config('activitylogger.log_name', 'activitylogger'))
            ->event($event)
            ->performedOn($on)
            ->causedBy($causer)
            ->withProperties($with)
            ->log(":causer.email(:causer.id) has $event Model");
    }

    public function logToFile(string $event, Model $on, ?Authenticatable $causer = null, ?array $with = null): void
    {
        if (empty($causer)) {
            $causerName = 'Unknown';
            $causerId = '🚫';
        } else {
            $causerName = $causer->email ?? $causer->name ?? get_class($causer);
            $causerId = $causer->getAuthIdentifier();
        }
        $onClass = get_class($on);
        $description = "{$causerName}({$causerId}) has $event Model {$onClass}#{$on->getKey()}";

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

    public function subscribe(Dispatcher $events): void
    {
        if (config('activitylogger.enabled', true) === false) {
            return;
        }

        $events->listen(
            'eloquent.created: *',
            fn (string $event, array $models) => $this->handleLogSave('created', array_pop($models))
        );

        $events->listen(
            'eloquent.updating: *',
            fn (string $event, array $models) => $this->handleUpdating('updating', array_pop($models))
        );

        $events->listen(
            'eloquent.updated: *',
            fn (string $event, array $models) => $this->handleLogSave('updated', array_pop($models))
        );

        $events->listen(
            'eloquent.deleted: *',
            fn (string $event, array $models) => $this->handleLogSave('deleted', array_pop($models))
        );

        $events->listen(
            'eloquent.restored: *',
            fn (string $event, array $models) => $this->handleLogSave('restored', array_pop($models))
        );
    }
}
