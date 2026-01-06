## From v3 to v4

```bash
composer require webqamdev/activity-logger "^4.0.0"
```

### Models configuration

To benefit from the standard Spatie log format (which includes old vs attributes changes) and to configure logging
options, you must now use the LogsActivity trait and implement `getActivitylogOptions` in your models.

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class YourModel extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
}
```

The package will continue to work even if you do not add the LogsActivity trait to your models. Basic events (created,
updated, deleted) will still be logged. However, without the trait, the logs will not contain the detailed attributes
(new values) and old (original values) keys required to see exactly what data has changed.

## From v2 to v3

``` bash
composer require webqamdev/activity-logger "^3.0.0"
```

### Models configuration

- The model attribute `$activity_hidden` has been renamed `$logAttributesToIgnore`
