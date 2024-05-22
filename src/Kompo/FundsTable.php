<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Fund;
use App\View\Traits\IsDashboardCard;
use Kompo\Table;

class FundsTable extends Table
{
    use IsDashboardCard;

    public function query()
    {
        return currentUnion()->funds();
    }

    public function top()
    {
        return $this->cardHeader('finance.default-funds', [
            !auth()->user()->can('create', new Fund()) ? null :
                _AddLink()->get('funds.form')
        ]);
    }

    public function headers()
    {
        return [
            _Th('general.name'),
            _Th('finance.default-allocation'),
            _Th(),
        ];
    }

    public function render($fund)
    {
    	return [
            !auth()->user()->can('update', $fund) ?

                _Html($fund->name) :

                _EditLink($fund->name)->get('funds.form', [
                    'id' => $fund->id
                ]),
            _Html($fund->allocation),
            !auth()->user()->can('delete', $fund) ?
                _Html() :
                _DeleteLink()->byKey($fund)->class('text-gray-600'),
    	];
    }
}
