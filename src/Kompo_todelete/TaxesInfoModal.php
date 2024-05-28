<?php

namespace Condoedge\Finance\Kompo;

use App\View\Modal;

class TaxesInfoModal extends Modal
{
	public $class = 'overflow-y-auto mini-scroll max-w-xl';
	public $style = 'max-height: 95vh';

  	protected $_Title = 'finance.tax-settings';

	public function body()
	{
		return [
			currentUnion()->tax_accounts_enabled ?
				_Html('finance.taxes-enabled-message') :
				_Html('finance.taxes-disabled-message')
		];
	}
}
