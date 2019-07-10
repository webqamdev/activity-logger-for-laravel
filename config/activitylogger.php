<?php

return [

    /*
     * If set to false, no activities will be saved.
     */

    'enabled' => env('MODEL_ACTIVITY_LOGGER_ENABLED', true),

    /*
     * If set to false, no activities will be saved to the database, only in files.
     */

    'to_database' => env('ACTIVITY_LOGGER_TO_DATABASE', true),

    /*
     * Configure logging channel. Driver is forced to 'daily'.
     */

    'channel' => [
        'path'  => storage_path('logs/activity.log'),
        'level' => 'debug',
        'days'  => 14,
    ],

    /*
     * Remove properties from logging.
     * Merged with Model->activity_hidden.
     */

    'properties_hidden' => [
        'password',
    ],

];
