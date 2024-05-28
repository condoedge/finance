<?php

namespace Condoedge\Finance\Kompo;

use App\Mail\ContributionAnnualNotification;
use App\Models\Condo\Unit;
use App\View\Modal;

class ContributionSendingAnnualModal extends Modal
{
	protected $_Title = 'finance.send-contribution';
	protected $_Icon = 'mail';

	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

    public $model = Unit::class;

    public function handle()
	{
		$invoiceSent = false;

        foreach($this->model->owners as $owner) {

            if ($owner->mainEmail()) {
                \Mail::to($owner)->send(
                    new ContributionAnnualNotification($owner, $this->model, request('message'))
                );

                $invoiceSent = true;
            }
        }

		if (!$invoiceSent) {
			abort(500, 'error.no-email-sent-valid-email');
		} else {
			return __('finance.contribution-sent');
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
            	->default(ContributionAnnualNotification::getContributionTextWithValues($this->model))
		);
	}

	public function rules()
	{
		return [
			'message' => 'required',
		];
	}
}
