<?php

return [

    /*
     * If set to false, no activities will be saved to the database.
     */

    'enabled' => env('MODEL_ACTIVITY_LOGGER_ENABLED', true),

    /*
     * Configure logging channel. Driver is forced to 'daily'.
     */

    'channel' => [
        'path'  => storage_path('logs/laravel.log'),
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
