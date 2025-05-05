<?php

use Condoedge\Finance\Models\MorphablesEnum;

if (!function_exists('getFinanceMorphableModel')) {
    function getFinanceMorphableModel($morphableType, $id)
    {
        $morphableType = MorphablesEnum::tryFrom($morphableType);

        if ($morphableType) {
            return $morphableType->getMorphableClass()::findOrFail($id);
        }

        throw new \InvalidArgumentException('Invalid morphable type: ' . $morphableType);
    }
}