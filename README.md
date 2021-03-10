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
By default, run thoses commands :

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="migrations"
php artisan migrate
```

You can optionally publish the config file with:

```bash
php artisan vendor:publish --provider="Webqamdev\ActivityLogger\ActivityLoggerServiceProvider" --tag="config"
```

## Usage

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
    public $activity_hidden = [
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
    
## About

This package using Laravel 5.8 is a plugin for auto-logging activities.

Gitlab repository : [Activity logger for Laravel](https://gitlab.webqam.fr/webqam/laravel-modules/activity-logger-for-laravel)
Github repository : [Activity logger for Laravel](https://github.com/webqamdev/activity-logger-for-laravel)
