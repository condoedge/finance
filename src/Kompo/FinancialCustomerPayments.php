<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\CustomerPaymentModel;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\MorphablesEnum;

use Condoedge\Utils\Kompo\Common\WhiteTable;

class FinancialCustomerPayments extends WhiteTable
{
    public $id = 'financial-customer-payments-table';

    protected $customerId;

    public function created()
    {
        $this->customerId = $this->prop('customer_id');
    }

    public function top()
    {
        return _FlexBetween(
            _Button('finance-create-payment')->selfGet('getCreatePaymentModal')->inModal(),
        );
    }

    public function query()
    {
        return CustomerPaymentModel::forCustomer($this->customerId);
    }

    public function headers()
    {
        return [
            _Th('finance.payment-date')->sort('payment_date'),
            _Th('finance.amount')->sort('amount'),
            _Th('finance.amount-left')->sort('amount_left'),
        ];
    }

    public function render(CustomerPayment $customerPayment)
    {
        return _TableRow(
            _HtmlDate($customerPayment->payment_date),
            _FinanceCurrency($customerPayment->amount),
            _FinanceCurrency($customerPayment->amount_left),
            _TripleDotsDropdown(
                $customerPayment->amount_left->lessThanOrEqual(0) ? null : _DropdownLink('finance-apply-payment')->selfGet('getApplyPaymentToInvoiceModal', [
                    'customer_payment_id' => $customerPayment->id,
                ])
                    ->inModal(),
            ),
        );
    }

    public function getApplyPaymentToInvoiceModal($paymentId)
    {
        return new ApplyPaymentToInvoiceModal(null, [
            'applicable_key' => MorphablesEnum::PAYMENT->value . '|' . $paymentId,
            'customer_id' => $this->customerId,
            'refresh_id' => $this->id,
        ]);
    }

    public function getCreatePaymentModal()
    {
        return new PaymentForm(null, [
            'customer_id' => $this->customerId,
            'refresh_id' => $this->id,
        ]);
    }
}
