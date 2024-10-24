<?php

namespace Webqamdev\ActivityLogger\Listeners;

use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Activity;
use Webqamdev\ActivityLogger\ActivityLoggerServiceProvider;

class ActivityLogger extends \Spatie\Activitylog\ActivityLogger
{
    public const CACHE_DURATION_IN_DAYS  = 1;
    public const CACHE_KEY_PURGE = 'flag_delete_logs';

    public function log(string $description): ?ActivityContract
    {
        $this->logFile($description);
        $model = $this->logDatabase($description);
        if (!empty($model)) {
            $this->purgeDatabase();
        }

        return $model;
    }

    public function logDatabase(string $description): ?ActivityContract
    {
        if (empty(config('activitylogger.to_database', true))) {
            $this->disableLogging();
            $this->activity = null;
            return null;
        }

        return parent::log($description);
    }

    public function logFile(string $action): void
    {
        $activity    = $this->getActivity();
        $causer      = $activity->causer;
        $by          = empty($causer) ? 'System' : sprintf('%s(%d)', $causer->email, $causer->id);
        $onId        = $activity->subject_id;
        $onClass     = $activity->subject_type;
        $description = sprintf(
            '%s has %s Model %s#%d',
            $by,
            $action,
            $onClass,
            $onId
        );

        ActivityLoggerServiceProvider::logger()
            ->info($description, $this->getActivity()->properties->toArray());
    }

    public function purgeDatabase(): void
    {
        if (Cache::has(ActivityLogger::CACHE_KEY_PURGE) === false) {
            Cache::put(ActivityLogger::CACHE_KEY_PURGE, time(), now()->addDays(self::CACHE_DURATION_IN_DAYS));
            Activity::query()
                ->where('created_at', '<=', now()->subDays(config('activitylogger.days_before_delete_log', 1)))
                ->delete();
        }
    }
}
