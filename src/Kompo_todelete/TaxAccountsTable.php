<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Kompo\Table;

class TaxAccountsTable extends Table
{
    public $class = 'dashboard-card';

    public function query()
    {
        return GlAccount::inUnionGl()->whereNotNull('tax_id');
    }

    public function top()
    {
        return _CardHeader('finance.union-tax-account', [
            //_AddLink()->get('tax-account.form') //Commented out until we go outside canada...
            _Link()->icon(_Sax('setting-2',20))
                ->selfCreate('getTaxSettingsModal')->inModal()
        ]);
    }

    public function headers()
    {
        return [
            _Th('general.name'),
            _Th('Number'),
            //_Th()
        ];
    }

    public function render($account)
    {
    	return _TableRow(
            _Html($account->name),
            _Html($account->number),
            //_DeleteLink()->byKey($account) //Commented out until we go outside canada...
        )->editInModal('tax-account.form', ['id' => $account->id]);
    }

    public function getTaxSettingsModal()
    {
        return new TaxSettingsModal(currentUnionId());
    }
}
