<?php

namespace Webqamdev\ActivityLogger;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Support\ActivityLogger as SpatieActivityLogger;
use Spatie\Activitylog\Support\ActivityLogStatus;
use Spatie\Activitylog\Support\CauserResolver;

class ActivityLogger extends SpatieActivityLogger
{
    public const string CHANNEL = 'activitylogger';

    public function __construct(Repository $config, ActivityLogStatus $logStatus, CauserResolver $causerResolver)
    {
        if ($config->get('activitylogger.enabled', true) === false) {
            $logStatus->disable();
        }

        parent::__construct($config, $logStatus, $causerResolver);
    }

    public function log(string $description, bool $forceDatabase = false): ?ActivityContract
    {
        if ($this->logStatus->disabled()) {
            return null;
        }

        $activity = $this->getActivity();

        $activity->description = $this->resolveDescription(
            $activity->description ?? $description,
            $activity,
        );

        $this->mutateActivityOnSubject($activity);

        $this->getLogger()->info($description, [
            'attribute_changes' => $activity->attribute_changes?->toArray() ?? [],
            'properties' => $activity->properties?->toArray() ?? [],
        ]);

        if ($forceDatabase || config('activitylogger.to_database', true) === true) {
            $activity->save();
        }

        $this->activity = null;

        return $activity;
    }

    public function getLogger(): LoggerInterface
    {
        if (config('logging.channels.'.self::CHANNEL) !== null) {
            return Log::channel(self::CHANNEL);
        }

        return Log::build([
            'driver' => 'daily',
            'path' => config('activitylogger.channel.path', storage_path('logs/activity.log')),
            'days' => config('activitylogger.channel.days', 14),
            'level' => config('activitylogger.channel.level', 'debug'),
            'permission' => config('activitylogger.channel.permission', 0644),
        ]);
    }

    protected function resolveDescription(string $description, ActivityContract $activity): string
    {
        return preg_replace_callback('/:[a-z0-9._-]+(?<![.])/i', function ($match) use ($activity) {
            $match = $match[0];

            $attribute = Str::before(Str::after($match, ':'), '.');

            if (! in_array($attribute, ['subject', 'causer', 'properties', 'attribute_changes'])) {
                return $match;
            }

            $propertyName = substr($match, strpos($match, '.') + 1);

            $attributeValue = $activity->$attribute;

            if (is_null($attributeValue)) {
                return $match;
            }

            return data_get($attributeValue, $propertyName, $match);
        }, $description);
    }

    protected function mutateActivityOnSubject(ActivityContract $activity): void
    {
        if (! isset($activity->subject) || ! $activity->subject instanceof Model) {
            return;
        }

        if (method_exists($activity->subject, 'beforeActivityLogged')) {
            $activity->subject->beforeActivityLogged($activity, $activity->event ?? '');

            return;
        }

        if (method_exists($activity->subject, 'tapActivity')) {
            $activity->subject->tapActivity($activity, $activity->event ?? '');
        }
    }
}
