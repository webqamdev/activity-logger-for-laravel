<?php

namespace Webqamdev\ActivityLogger\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use Spatie\Activitylog\LogOptions;

trait LogsActivity
{
    use \Spatie\Activitylog\Traits\LogsActivity;

    private static Collection $activityLoggerClones;

    public function getActivitylogOptions(): LogOptions
    {
        $except = $this->logAttributesToIgnore ?? config('activitylogger.properties_hidden', []);
        return LogOptions::defaults()
            ->useLogName(config('activitylogger.log_name', 'activitylogger'))
            ->logAll()
            ->logExcept($except);
    }

    public static function cloneToActivityLogger(Model $source): static
    {
        if (empty(static::$activityLoggerClones)) {
            static::$activityLoggerClones = collect();
        }
        if (static::$activityLoggerClones->where('source', $source)->isEmpty()) {
            static::$activityLoggerClones[] = [
                'source' => $source,
                'clone' => new static(),
            ];
        }
        $result = static::$activityLoggerClones->where('source', $source)->first()['clone'];

        // Use reflection to access protected properties and clone them
        $sourceReflection = new ReflectionClass($source);
        $resultReflection = new ReflectionClass($result);

        // Get all properties including protected ones
        foreach ($sourceReflection->getProperties() as $property) {
            $property->setAccessible(true);  // Make the property accessible

            // Clone the value from Source to Result
            if ($resultReflection->hasProperty($property->getName())) {
                $resultProperty = $resultReflection->getProperty($property->getName());
                $resultProperty->setAccessible(true);
                $resultProperty->setValue($result, $property->getValue($source));
            } else {
                // If Result doesn't explicitly have the property (for public ones)
                $result->{$property->getName()} = $property->getValue($source);
            }
        }



        return $result;
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return parent::getTable();
    }

    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'logActivity') && Str::endsWith($method, 'Event')) {
            $event = strtolower(Str::before(Str::after($method, 'logActivity'), 'Event'));
            return $this->fireModelEvent($event, false);
        }

        return parent::__call($method, $parameters);
    }
}
