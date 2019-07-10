**This document is for internal use only. It is confidential and the property of Webqam. It may not be reproduced or transmitted in whole or in part without the prior written consent of Webqam. / Ce document est à usage interne uniquement. Il est confidentiel et la propriété de Webqam. Il ne peut être reproduit ou transmis en tout ou partie sans l'accord préalable et écrit de Webqam.**

# Log activity inside your Laravel app

The `webqam/activity-logger` package automatically log model changes from users into database and log files.

## Dependencies

This package use [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) to store logs in database.
Feel free to configure it if needed or just follow [Installation](#installation) instructions.

## Installation

You can install the package via composer:

```bash
composer config repositories.activity-logger git git@gitlab.webqam.fr:webqam/laravel-modules/activity-logger-for-laravel.git
composer require webqam/activity-logger
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
php artisan vendor:publish --provider="Webqam\ActivityLogger\ActivityLoggerServiceProvider" --tag="config"
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
    
## About

This package using Laravel 5.8 is a plugin for auto-logging activities.

Gitlab repository : [Activity logger for Laravel](https://gitlab.webqam.fr/webqam/laravel-modules/activity-logger-for-laravel)
