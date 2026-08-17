<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Payments\CreateAppliesForMultipleInvoiceDto;
use Condoedge\Finance\Models\InvoiceApply;
use Condoedge\Finance\Models\MorphablesEnum;
use Condoedge\Finance\Services\Payment\PaymentServiceInterface;
use Condoedge\Utils\Kompo\Plugins\FormCanHaveTableWithFields;

class ApplyPaymentToInvoiceModal extends Modal
{
    protected $plugins = [
        FormCanHaveTableWithFields::class,
    ];

    public $_Title = 'finance-apply-payment-to-invoice';

    public $class = 'max-w-6xl';

    protected $invoiceId;
    protected $paymentId;
    protected $customerId;
    protected $refreshId;

    protected $applicableType;
    protected $applicableId;
    protected $applicableModel;

    protected $applicableKey;

    public function created()
    {
        $this->customerId = $this->prop('customer_id');
        $this->invoiceId = $this->prop('invoice_id');
        $this->applicableKey = $this->prop('applicable_key');

        $this->refreshId = $this->prop('refresh_id');

        if (!$this->customerId || $this->applicableKey) {
            $this->parseApplicable($this->applicableKey);

            $this->customerId = $this->applicableModel->customer_id;
        }

        // Either entry point into the credit flow: opened on a credit, or opened from an
        // invoice looking for one. Without the second the title still says "payment".
        if ($this->applicableType == MorphablesEnum::CREDIT->value || $this->prop('crediting')) {
            $this->_Title = 'finance-apply-credit';
        }
    }

    public function handle(PaymentServiceInterface $paymentService)
    {
        $invoicesIds = $this->getInvoicesIdsToBeApplied();
        $amounts = collect(getTableInputValues('amount_applied_to_', $invoicesIds));

        $this->parseApplicable();

        // This is validating the information, but we also have a trigger to not allow to apply more than the left amount
        $paymentService->applyPaymentToInvoices(new CreateAppliesForMultipleInvoiceDto([
            'apply_date' => request('apply_date'),
            'applicable' => $this->applicableModel,
            'applicable_type' => (int) $this->applicableType,
            'amounts_to_apply' => $amounts->map(function ($amount, $invoiceId) {
                return [
                    'id' => $invoiceId,
                    'amount_applied' => $amount,
                ];
            })->all(),
        ]));
    }

    public function body()
    {
        return _CardLevel4(
            _Flex(
                _Rows(
                    _Select('finance-invoiced-to')->name('customer_id', false)->class('!mb-0')
                        ->options(CustomerModel::forTeam(currentTeamId())->pluck('name', 'id'))
                        ->default($this->customerId)
                        ->onChange(
                            fn ($e) => $e->selfGet('getApplicableOptions')->inPanel('applicables-panel') &&
                                $e->selfGet('getInvoicesToBeApliedTable')->inPanel('invoice-to-be-applied')
                        ),
                    _Panel(
                        !$this->customerId ? null : $this->getApplicableOptions($this->customerId)
                    )->id('applicables-panel'),
                )->class('gap-2 flex-1'),
                _Panel(
                    $this->getPaymentInfo($this->applicableKey),
                )->id('payment-info')->class('h-full flex-1'),
            )->class('items-stretch gap-4'),
            _Date('finance.apply-date')->name('apply_date')->default(now())->required(),
            _ErrorField()->name('amounts_to_apply', false)->noInputWrapper()->class('!my-0'),
            _Panel(
                $this->getInvoicesToBeApliedTable($this->customerId),
            )->id('invoice-to-be-applied'),
            _FlexEnd(
                _SubmitButton('finance.save')->refresh($this->refreshId)->closeModal(),
            )->class('mt-4'),
        )->p4();
    }

    public function getInvoicesToBeApliedTable($customerId = null)
    {
        return new CustomerInvoicesToBeAppliedTable([
            'customer_id' => $customerId,
            // Opened from an invoice: tick that row so the operator only picks the credit.
            'preselect_invoice_id' => $this->invoiceId,
        ]);
    }

    public function getPaymentInfo($applicable = null)
    {
        $this->parseApplicable($applicable);

        return _CardLevel5(
            _FlexBetween(
                _Html('finance-payment-amount')->class('font-semibold text-gray-600'),
                _FinanceCurrency($this->applicableModel->applicable_total_amount ?? 0)->class('font-bold text-3xl text-gray-800'),
            )->class('!items-end flex-1'),
            _FlexBetween(
                _Html('finance-remaining')->class('font-semibold text-gray-600'),
                _FinanceCurrency($this->applicableModel->applicable_amount_left ?? 0)->class('font-bold text-3xl text-gray-800'),
            )->class('!items-end flex-1 mb-4'),
        )->class('h-full gap-4 py-8 !px-6 justify-center');
    }

    public function getApplicableOptions($customerId)
    {
        if (!$customerId) {
            return null;
        }

        return _Select('finance.customer-payment')->name('applicable', false)
            ->selfGet('getPaymentInfo')->inPanel('payment-info')
            ->default($this->applicableKey)
            ->options(InvoiceApply::getAllApplicablesRecords($customerId)
                ->mapWithKeys(fn ($i) => [
                    $i->applicable_type . '|' . $i->applicable_id  => $i->applicable_name . ' (' . finance_currency($i->applicable_amount_left) . ')'
                ]));
    }

    protected function parseApplicable($applicable = null)
    {
        $applicable = request('applicable') ?? $applicable;

        if (!$applicable) {
            return;
        }

        $applicableExploded = explode('|', $applicable);

        if (!$applicableExploded || count($applicableExploded) !== 2) {
            return;
        }

        $this->applicableType = $applicableExploded[0];
        $this->applicableId = $applicableExploded[1];

        $this->applicableModel = getFinanceMorphableModel($this->applicableType, $this->applicableId);
    }

    protected function getInvoicesIdsToBeApplied()
    {
        return collect(getTableInputValues('apply_to_'))->filter()->keys()->all();
    }

    public function rules()
    {
        return [
            'apply_date' => 'required|date',
            'applicable' => 'required',
            'apply_to_*' => 'required',
        ];
    }
}
