<?php

namespace Condoedge\Finance\Kompo;

trait MorphManyChargeablesSelect
{
    /* ELEMENTS */
    public function getChargeablesSelect()
    {
        return _Select()->placeholder('finance-add-new-item')->name('chargeable')
            ->searchOptions(0, 'searchChargeables')
            ->class('mb-0 py-4 px-8 bg-level5 rounded-b-2xl border-t')
            ->resetAfterChange();
    }

    /* ACTIONS */
    public function searchChargeables($search)
    {
        /* Until we connect the market module
        $products = \App\Models\Market\Product::forTeam()->searchName($search)->limit(10)->pluck('name_pd', 'id')->mapWithKeys(fn($label, $id) => [
            'product|'.$id => $label,
        ]);

        $services = \App\Models\Market\Service::forTeam()->searchName($search)->limit(10)->pluck('name_sv', 'id')->mapWithKeys(fn($label, $id) => [
            'service|'.$id => $label,
        ]);
        */

        $products = collect();
        $services = collect();

        return collect([
            0 => _Html('finance-create-new-item')->class('text-greenmain font-medium text-opacity-75')
        ])->union($products)->union($services);
    }
}
