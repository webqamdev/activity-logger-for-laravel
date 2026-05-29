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
