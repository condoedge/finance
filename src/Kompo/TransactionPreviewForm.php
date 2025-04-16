<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\Transaction;
use Condoedge\Utils\Kompo\Common\Modal;

class TransactionPreviewForm extends Modal
{
	public $model = Transaction::class;

	public $_Title = 'finance-preview-transaction';
	public $_Icon = 'clipboard-text';


    public function headerButtons()
    {
        return;
    }

	public function body()
	{
		$dateValue = $this->model->transacted_at;

		$parentLink = ($parentLink = $this->model->parentLink()) ? $parentLink->inNewTab()->outlined() : null;
		$dateField = _MiniLabelValue('finance-transaction-date', $dateValue);
		$descField = _MiniLabelValue('finance-description', $this->model->description);

		$paymentNumber = $this->model->getPaymentNumber(); 
		$paymentNumber = $paymentNumber ? _MiniLabelValue('Payment #', $paymentNumber) : null;

		return [
			_Rows(
				_FlexBetween(
					_Flex4(
						$parentLink,
						$dateField,
						$descField,
						$paymentNumber,
					),
					_Link('finance-go-to-transaction')->button()->icon('external-link')->class('mt-1 ml-4')
						->href('finance.transaction-form', ['id' => $this->model->id])->inNewTab(),
				)->class('mb-4'),
				_FlexBetween(
					_Flex4(
						_TitleMini('finance-entries'),
						_TitleMini('#'.$this->model->id),
					),
					$this->model->voidPill(),
				)->class('mb-2'),
				new TransactionEntriesTable(['transaction_id' => $this->model->id ]),
			)
		];
	}
}
