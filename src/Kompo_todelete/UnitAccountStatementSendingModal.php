<?php

namespace Condoedge\Finance\Kompo;

use App\Mail\UnitAccountStatementNotification;
use App\Models\Condo\Unit;
use App\View\Modal;
use Illuminate\Support\Facades\Mail;

class UnitAccountStatementSendingModal extends Modal
{
	protected $_Title = 'finance.send-unit-account-statement';
	protected $_Icon = 'mail';

	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

    public $model = Unit::class;

    protected $startDate;
    protected $endDate;

    public function created()
    {
    	$this->startDate = $this->prop('start_date');
    	$this->endDate = $this->prop('end_date');
    }

    public function handle()
	{
		$statementSent = false;

        foreach($this->model->owners as $owner) {

            if ($owner->mainEmail()) {
                Mail::to($owner)->send(
                    new UnitAccountStatementNotification($owner, $this->model, $this->startDate, $this->endDate, request('message'))
                );

                $statementSent = true;
            }
        }

		if (!$statementSent) {
			abort(500, __('finance.no-email-sent-no-email'));
		} else {
			return __('finance.statement-sent');
		}
	}

	public function headerButtons()
	{
		return _SubmitButton('Send')->inAlert()->closeModal();
	}

	public function body()
	{
		return _Rows(
            _CKEditor('finance.customize-message-here')
            	->name('message')
            	->default(UnitAccountStatementNotification::getDefaultTextWithValues($this->model))
		);
	}

	public function rules()
	{
		return [
			'message' => 'required',
		];
	}
}
