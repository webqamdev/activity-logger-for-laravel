<?php

namespace Webqamdev\ActivityLogger\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity as SpatieLogsActivity;

/**
 * Keeps Spatie's change tracking (old/attributes) but lets this package's
 * listener be the only one writing activities, avoiding duplicate logs.
 */
trait LogsActivity
{
    use SpatieLogsActivity {
        shouldLogEvent as public shouldLogActivityEvent;
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return false;
    }
}
