## From webqamdev/activity-logger v5 to v6

```bash
composer require webqamdev/activity-logger "^6.0.0"
```

### Models configuration

Replace Spatie's trait with the package one, and remove `$enableLoggingModelsEvents = false` if present (it conflicts
with the trait property and triggers a fatal error):

```diff
-use Spatie\Activitylog\Models\Concerns\LogsActivity;
+use Webqamdev\ActivityLogger\Concerns\LogsActivity;

 class YourModel extends Model
 {
     use LogsActivity;
-
-    public bool $enableLoggingModelsEvents = false;
```

Models still using Spatie's trait keep working, but each event is logged twice (once by Spatie, once by this package).

With the package trait, the model's `LogOptions` are now honoured: `useLogName()`, `dontLogIfAttributesChangedOnly()`
and `disableLogging()`.

### Removed

- The container binding of `Illuminate\Contracts\Auth\Authenticatable` to the user model. It overrode Laravel's own
  binding (the authenticated user), so activities had no causer. If your app relied on it, bind it yourself.
- The `user_model` config key: remove it from your published `config/activitylogger.php`.

### Behaviour changes

- `restore()` logs a single `restored` activity instead of `updated` + `restored`.
- The activity model set in `activitylog.activity_model` is used to detect activities and to purge old logs.

## From webqamdev/activity-logger v4 to v5 (Spatie activitylog v5)

```bash
composer require webqamdev/activity-logger "^5.0.0"
```

This release requires **PHP 8.4+**, **Laravel 12+**, and **spatie/laravel-activitylog ^5.0**.

### Spatie changes

See [Spatie UPGRADING.md](https://github.com/spatie/laravel-activitylog/blob/main/UPGRADING.md#from-v4-to-v5).

## From v3 to v4

```bash
composer require webqamdev/activity-logger "^4.0.0"
```

### Models configuration

To benefit from the standard Spatie log format (which includes old vs attributes changes) and to configure logging
options, you must now use the `LogsActivity` trait and implement `getActivitylogOptions` in your models.

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class YourModel extends Model
{
    use LogsActivity;

    public bool $enableLoggingModelsEvents = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
}
```

The package will continue to work even if you do not add the `LogsActivity` trait to your models. Basic events (created,
updated, deleted) will still be logged. However, without the trait, the logs will not contain the detailed `attribute_changes`
(`attributes` / `old` keys) required to see exactly what data has changed.

## From v2 to v3

``` bash
composer require webqamdev/activity-logger "^3.0.0"
```

### Models configuration

- The model attribute `$activity_hidden` has been renamed `$logAttributesToIgnore`
