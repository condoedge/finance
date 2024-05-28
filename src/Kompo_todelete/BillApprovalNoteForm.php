<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Bill;
use App\View\Modal;

class BillApprovalNoteForm extends Modal
{
	protected $_Title = 'Notes';
	protected $_Icon = 'library';

	public $model = Bill::class;

	public function body()
	{
		return [
			_Textarea('general.notes')->name('notes'),
			_SubmitButton('general.save')->closeModal(),
		];
	}
}
