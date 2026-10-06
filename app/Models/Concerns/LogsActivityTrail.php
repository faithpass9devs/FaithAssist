<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsActivityTrail
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(strtolower(class_basename(static::class)))
            ->logAll()
            ->logExcept([
                'created_by',
                'updated_by',
                'deleted_by',
                'deleted_at',
                'password',
                'remember_token',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->event = $eventName;

        $properties = $activity->properties?->toArray() ?? [];
        $properties['module'] = class_basename($this);

        $request = request();

        if ($request->hasSession()) {
            $properties['session_id'] = $request->session()->getId();
        }

        if (! app()->runningInConsole()) {
            $properties['ip_address'] = $request->ip();
            $properties['user_agent'] = $request->userAgent();
            $properties['url'] = $request->fullUrl();
        }

        $activity->properties = $properties;
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return sprintf('%s %s #%s', class_basename($this), $eventName, $this->getKey() ?? 'new');
    }
}