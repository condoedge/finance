<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use Condoedge\Finance\Models\Entry;
use App\View\Modal;

class TaxSettingsModal extends Modal
{
    public $model = Union::class;

	public $class = 'overflow-y-auto mini-scroll max-w-xl';
	public $style = 'max-height: 95vh';

  	protected $_Title = 'finance.tax-accounts';
  	protected $_Icon = 'dollar';

  	public function beforeSave()
  	{
  		if (!request('tax_accounts_enabled') && Entry::whereHas('account', fn($q) => $q->forUnion()->forTax())->count()) {
  			abort(403, __('error.tax-account-no-void-cant-be-disabled'));
  		}
  	}

	public function body()
	{
		return [
			_Html('finance.enable-tax-accounts-sub1')->class('mb-4 card-gray-100 p-4'),
			_Toggle('finance.enable-tax-accounts')->name('tax_accounts_enabled')->submit()
		];
	}
}
