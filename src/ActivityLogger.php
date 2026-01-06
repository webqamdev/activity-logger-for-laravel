<?php

namespace Webqamdev\ActivityLogger;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Spatie\Activitylog\ActivityLogger as SpatieActivityLogger;

class ActivityLogger extends SpatieActivityLogger
{
    public function logFile(string $description): void
    {
        if ($this->logStatus->disabled()) {
            return;
        }

        $activity = $this->activity;

        $description = $this->replacePlaceholders(
            $activity->description ?? $description,
            $activity,
        );

        if (isset($activity->subject) && method_exists($activity->subject, 'tapActivity')) {
            $this->tap([$activity->subject, 'tapActivity'], $activity->event ?? '');
        }

        $this->getLogger()->info($description, $activity->properties->toArray());

        $this->activity = null;
    }

    protected function getLogger(): Logger
    {
        $name = config('activitylogger.channel.name', 'user');
        $path = config('activitylogger.channel.path', storage_path('logs/activity.log'));
        $days = config('activitylogger.channel.days', 14);
        $level = config('activitylogger.channel.level', 'debug');
        $permission = config('activitylogger.channel.permission', 0644);

        // Make logger
        $log = new Logger($name);
        $log->pushHandler(new RotatingFileHandler($path, $days, $level, true, $permission));

        return $log;
    }
}
