# Log activity inside your Laravel app

The `webqamdev/activity-logger` package automatically log model changes from users into database and log files.

## Dependencies

This package use [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) to store logs in database.
Feel free to configure it if needed or just follow [Installation](#installation) instructions.

## Installation

You can install the package via composer:

```bash
composer require webqamdev/activity-logger
```

The package will automatically register itself.

Configure [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog/blob/master/README.md#installation).
By default, run those commands :

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

You can optionally publish the config file with:

```bash
php artisan vendor:publish --provider="Webqamdev\ActivityLogger\ActivityLoggerServiceProvider" --tag="config"
```

## Usage

### Model Configuration & Detailed Logs

To enable detailed logging and ensure the logs follow the standard Spatie format (recording changes with `old` and
`attributes` values in `attribute_changes`), your model must use the `Spatie\Activitylog\Models\Concerns\LogsActivity`
trait and implement the `getActivitylogOptions` method.

Set `$enableLoggingModelsEvents = false` on the model to avoid duplicate logs (this package already listens to Eloquent events).

This is **necessary** to achieve the following structure in your logs (showing what changed):

```php
[
   'old' => [
        'name' => 'original name',
        'text' => 'Lorum',
    ],
    'attributes' => [
        'name' => 'updated name',
        'text' => 'Lorum',
    ],
]
```

Example:

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Model
{
    use LogsActivity;

    public bool $enableLoggingModelsEvents = false;

    /**
     * Configure the activity logging rules.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()        // Log all attributes...
            ->logOnlyDirty(); // ...but only store changes
    }
}
```

### Globally hide a property

Publish config file. Then add entries to `properties_hidden` array.

### Hide a Model property

Create your model normally, then define hidden properties.

```php
class User extends Model {

    /**
     * The attributes that shouldn't be logged in activity logger.
     * 
     * @var array 
     */
    public $logAttributesToIgnore = [
        'password',
        'phone',
    ];

     ...
}
```

### Disable logs into database

Add `ACTIVITY_LOGGER_TO_DATABASE=false` to your `.env` file will prevent logger from writing into database.

### Change files permission

If not already done, publish config file:

```bash
php artisan vendor:publish --provider="Webqamdev\ActivityLogger\ActivityLoggerServiceProvider" --tag="config"
```

Add `channel.permission` to your `config/activitylogger.php` file like this exemple:

```php
'channel' => [
    'path'       => storage_path('logs/activity.log'),
    'level'      => 'debug',
    'days'       => 14,
    'permission' => 0644, // Default value, equivalent to bash's rw-r--r--
],
```

## Upgrading

Please see [UPGRADING](UPGRADING.md) for details.

## About

This package requires Laravel 12+ and PHP 8.4+. It is a plugin for auto-logging model activities.

Gitlab
repository : [Activity logger for Laravel](https://gitlab.webqam.fr/webqam/laravel-modules/activity-logger-for-laravel)
Github repository : [Activity logger for Laravel](https://github.com/webqamdev/activity-logger-for-laravel)
