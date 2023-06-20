<?php

return [

    // Fully qualified namespace of the User model
    'user_model' => App\User::class,

    /*
     * If set to false, no activities will be saved.
     */

    'enabled' => env('MODEL_ACTIVITY_LOGGER_ENABLED', true),

    /*
     * If set to false, no activities will be saved to the database, only in files.
     */

    'to_database' => env('ACTIVITY_LOGGER_TO_DATABASE', true),

    /*
    * configure the number of days before deleting the logs (default for DB => 90, default for files => 14).
    */

    'days_before_delete_log' => env('DAYS_BEFORE_DELETE_LOG', 90),

    /*
     * Configure logging channel. Driver is forced to 'daily'.
     */

    'channel' => [
        'path'  => storage_path('logs/activity.log'),
        'level' => 'debug',
        'days'  => env('DAYS_BEFORE_DELETE_LOG', 90),
    ],

    /*
     * Remove properties from logging.
     * Merged with Model->activity_hidden.
     */

    'properties_hidden' => [
        'password',
    ],

];
