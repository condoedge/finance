<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use App\View\Modal;

class BudgetApprovalConfirmation extends Modal
{
	public $class = 'overflow-y-auto mini-scroll bg-gray-100 rounded-2xl';
	public $style = 'max-height: 95vh;min-width:400px';

	protected $_Title = 'Success';
	protected $_Icon = 'check-circle';

	public $model = Budget::class;

	public function body()
	{
		return [
			_Html('finance.budget-approved-create-contributions'),
			_Link('finance.see-created-contributions')->button()->class('mt-4')
				->href('budget-preview.view', [
					'budget_id' => $this->model->id,
				])
		];
	}
}
