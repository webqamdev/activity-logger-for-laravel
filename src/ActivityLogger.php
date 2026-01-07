<?php

namespace Webqamdev\ActivityLogger;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Spatie\Activitylog\ActivityLogger as SpatieActivityLogger;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class ActivityLogger extends SpatieActivityLogger
{
    public function log(string $description): ?ActivityContract
    {
        if ($this->logStatus->disabled()) {
            return null;
        }

        $activity = $this->activity;

        $activity->description = $this->replacePlaceholders(
            $activity->description ?? $description,
            $activity,
        );

        if (isset($activity->subject) && method_exists($activity->subject, 'tapActivity')) {
            $this->tap([$activity->subject, 'tapActivity'], $activity->event ?? '');
        }

        $this->getLogger()->info($description, $activity->properties->toArray());

        if (config('activitylogger.to_database', true) === true) {
            $activity->save();
        }

        $this->activity = null;

        return $activity;
    }

    protected function getLogger(): LoggerInterface
    {
        return Log::build([
            'driver' => 'daily',
            'path' => config('activitylogger.channel.path', storage_path('logs/activity.log')),
            'days' => config('activitylogger.channel.days', 14),
            'level' => config('activitylogger.channel.level', 'debug'),
            'permission' => config('activitylogger.channel.permission', 0644),
        ]);
    }
}
