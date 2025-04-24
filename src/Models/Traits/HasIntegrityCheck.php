<?php

namespace Condoedge\Finance\Models\Traits;

use Condoedge\Finance\Facades\IntegrityChecker;

trait HasIntegrityCheck
{
    use HasRelationsManager;

    /**
     * Initialize integrity-related events.
     * This method is automatically called from the AbstractFinanceModel boot.
     *
     * @return void
     */
    public static function bootHasIntegrityCheck()
    {
        static::saved(function ($model) {
            IntegrityChecker::checkChildrenThenModel(static::class, [$model->id]);
            IntegrityChecker::checkModelThenParents(static::class, [$model->id]);
        });

        static::deleted(function ($model) {
            IntegrityChecker::checkChildrenThenModel(static::class, [$model->id]);
            IntegrityChecker::checkModelThenParents(static::class, [$model->id]);
        });
    }

    public static function getMainClass(): string
    {
        $calledClass = static::class;
        $allClasses = [static::class, ...class_parents($calledClass)];
        
        foreach ($allClasses as $class) {
            if (strpos($class, 'Condoedge\\Finance\\Models\\') === 0) {
                return $class;
            }
        }

        return $calledClass;
    }
}