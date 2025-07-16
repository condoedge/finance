<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\ProductModel;

trait MorphManyChargeablesSelect
{
    /* ELEMENTS */
    public function getChargeablesSelect()
    {
        return _Select()->placeholder('finance-add-new-item')->name('product_id')
            ->searchOptions(0, 'searchChargeables')
            ->class('mb-0 py-4 px-8 bg-level5 rounded-b-2xl border-t')
            ->resetAfterChange();
    }

    /* ACTIONS */
    public function searchChargeables($search)
    {
        $products = ProductModel::search($search)
            ->forTeam()
            ->isTemplate()
            ->pluck('product_name', 'id')
            ->unique();

        return collect([
            -2 => _Html('finance-create-an-unique-item-sale')->class('text-greenmain font-medium text-opacity-75'),
            -1 => _Html('finance-create-new-item')->class('text-greenmain font-medium text-opacity-75')
        ])->union($products);
    }
}
