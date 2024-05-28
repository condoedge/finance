<?php

namespace Condoedge\Finance\Kompo;

use App\Mail\ContributionNotification;
use Condoedge\Finance\Models\Invoice;
use App\View\Modal;
use Illuminate\Support\Carbon;

class ContributionSendingModal extends Modal
{
	protected $_Title = 'finance.send-contributions';
	protected $_Icon = 'mail';

	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

    protected $panelId = 'units-emails-mini-table';

    protected $invoiceDates;

    public function created()
    {
    	$this->invoiceDates = $this->getBaseInvoicesQuery()->where('due_at', '<=', Carbon::now()->addDays(28))
    		->select('invoiced_at')->distinct()->orderByDesc('invoiced_at')->pluck('invoiced_at')->mapWithKeys(fn($date) => [
    			$date->format('Y-m-d') => $date->translatedFormat('d M Y'),
    		]);
    }

    protected function getBaseInvoicesQuery()
    {
    	return Invoice::whereSendable(currentUnion()->id)->where('customer_type', 'unit')
    		->with('customer.owners.emails');
    }

    protected function getInvoicesForDate($invoiceDate)
    {
    	return $this->getBaseInvoicesQuery()->forDate($invoiceDate)
    		->orderBy('customer_id'); //in case of adjustment invoice on the same date
    }

	public function handle()
	{
		$this->getInvoicesForDate(request('send_date'))->get()->each(
			fn($invoice) => $invoice->sendEmail(request('message'))
        );
	}

	public function headerButtons()
	{
		return $this->invoiceDates->count() ? _SubmitButton('finance.send-invoices')->closeModal() : null;
	}

	public function body()
	{
		if (!$this->invoiceDates->count()) {
			return _Html('finance.no-invoice-due-next-10');
		}

		$defaultDate = $this->invoiceDates->take(1)->keys()->first();

		return _Columns(
			_Rows(
	            _Select('finance.send-invoices-for-date')->name('send_date')
	            	->options($this->invoiceDates)->default($defaultDate)
	            	->selfGet('getMonthInvoicesTable')->inPanel($this->panelId),

	            _Html('finance.check-all-invoices-one-email')
	            	->class('card-gray-100 p-4'),

	            _CKEditor('finance.customize-message-here')
	            	->name('message')
	            	->default(ContributionNotification::getContributionDefaultText())
			),
			_Rows(
				_Panel(
					$this->getMonthInvoicesTable($defaultDate)
				)->id($this->panelId)
				->class('overflow-y-auto mini-scroll')
				->style('height:calc(95vh - 100px)')
			)
		);
	}

	public function rules()
	{
		return [
			'send_date' => 'required|date',
			'message' => 'required',
		];
	}

	public function getMonthInvoicesTable($invoiceDate = null)
	{
		$invoices = $this->getInvoicesForDate($invoiceDate ?: request('send_date'));

		if (!$invoices->count()) {
			return _Html('finance.no-draft-or-approved-invoice')->class('card-gray-100 p-4');
		}

		return _Rows(
				$invoices->get()->map(
					fn($invoice) => _FlexBetween(
			            _Rows(
			            	_Flex4(
			            		_Html($invoice->customer_label)->class('font-medium'),
			                	_Html(dateStr($invoice->due_at))->class('text-gray-600'),
			            	),
			                _EmailHtml($invoice->customer->owners->map(fn($owner) => $owner->mainEmail())->implode('<br>')),
			            )->class('space-y-2'),
			            _Rows(
			                _Html('finance.amount-due')->class('text-xxs font-bold text-gray-600'),
			                _Currency($invoice->due_amount),
			                _Flex(
			                    _Html('/'),
			                    _Currency($invoice->total_amount),
			                )->class('space-x-2 text-gray-600'),
			            )->class('items-end'),
			        )->class('p-2 text-xs border-b border-gray-200')
				)
			);
	}
}
