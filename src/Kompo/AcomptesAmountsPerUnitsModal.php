<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Acompte;
use Kompo\Table;
use App\View\Traits\IsDashboardModal;

class AcomptesAmountsPerUnitsModal extends Table
{
    use IsDashboardModal;

	protected $accountId;
	protected $account;

	protected $_Title = 'finance.advance-payments-per-units';
	protected $_Icon = 'money';

	public $class = 'max-w-2xl overflow-y-auto mini-scroll mb-4';
	public $style = 'max-height: 95vh';

	public function query()
	{
		return Acompte::with('unit')->whereIn('unit_id', currentUnion()->units()->pluck('id'));
	}

    public function top()
    {
        return $this->modalHeader($this->_Title, $this->_Icon, [
        	_SubmitButton()
        ]);
    }

    public function headers()
    {
    	return [
    		_Th('Unit'),
    		_Th('Amount'),
    	];
    }

    public function render($acompte)
    {
    	return _TableRow(
    		_Html($acompte->unit->display),
    		_Currency($acompte->amount),
    	);
    }
}
