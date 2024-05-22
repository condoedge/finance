<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Bank;
use App\View\Traits\IsDashboardCard;
use Kompo\Query;

class BanksTable extends Query
{
    use IsDashboardCard;

    public $layout = 'Table';

    protected $editRoute = 'banks.form';

    public function query()
    {
        return Bank::where('union_id', currentUnion()->id)->with('account');
    }

    public function top()
    {
        return $this->cardHeader('finance.union-bank-account', [
            _AddLink()->get($this->editRoute)
        ]);
    }

    public function headers()
    {
        return [
            _Th('general.name'),
            _Th('Account'),
            _Th('Default'),
            _Th()
        ];
    }

    public function render($bank)
    {
    	return _TableRow(
            _Html($bank->display),
            _Html($bank->account?->display),
            _Html($bank->default_bank ? '⭐' : '')->class('text-center'),
            _FlexEnd(
                _DeleteLink()->byKey($bank),
            ),
        )->editInModal($this->editRoute, [
            'id' => $bank->id
        ]);
    }
}
