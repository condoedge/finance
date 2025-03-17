<?php

namespace Condoedge\Finance\Models\Traits;

trait HasEventsOnDbInteraction
{
    /**
     * Initialize integrity-related events.
     * This method is automatically called from the AbstractFinanceModel boot.
     *
     * @return void
     */
    public static function bootHasEventsOnDbInteraction()
    {
        static::saved(function ($model) {
            if ($model->wasRecentlyCreated) {
                $eventClass = $model->getCreatedEventClass();
                
                if ($eventClass && class_exists($eventClass)) {
                    event(new $eventClass($model));
                }
            }
        });
    }

    /**
     * Get the creation event class for the current model.
     * This allows concrete models to provide their own events.
     *
     * @return string|null
     */
    protected function getCreatedEventClass()
    {
        // By default, can define a generic event based on class name
        $className = class_basename($this);
        $eventClass = "\\Condoedge\\Finance\\Events\\{$className}Created";
        
        return class_exists($eventClass) ? $eventClass : null;
    }
}