<?php

namespace Condoedge\Finance\Kompo;

use App\Mail\ContributionNotification;
use Condoedge\Finance\Models\Invoice;
use App\View\Modal;
use Illuminate\Support\Carbon;

class ContributionSendingSingleModal extends Modal
{
	protected $_Title = 'finance.send-contribution';
	protected $_Icon = 'mail';

	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

    public $model = Invoice::class;

    public function handle()
	{
		$this->model->markApprovedWithJournalEntries(); //Approve if not yet done

		if (!$this->model->sendEmail(request('message'), 'send')) {
			abort(500, 'error.no-email-sent-valid-email');
		} else {
			return __('finance.contribution-sent');
		}
	}

	public function headerButtons()
	{
		return _SubmitButton('Send')->inAlert()->refresh('charge-stage-page')->closeModal();
	}

	public function body()
	{
		return _Rows(
            _CKEditor('finance.customize-message-here')
            	->name('message')
            	->default(ContributionNotification::getContributionTextWithValues($this->model))
		);
	}

	public function rules()
	{
		return [
			'message' => 'required',
		];
	}
}
