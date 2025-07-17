<?php

namespace Condoedge\Finance\Services\Payment;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Facades\InvoicePaymentModel;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Dto\Payments\CreateAppliesForMultipleInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateApplyForInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceApply;
use Condoedge\Finance\Models\MorphablesEnum;
use Condoedge\Finance\Models\PaymentInstallmentPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Service Implementation
 *
 * Handles all payment business logic including creation, application to invoices,
 * validation, and amount calculations.
 *
 * This implementation can be easily overridden by binding a custom
 * implementation to the PaymentServiceInterface in your service provider.
 */
class PaymentService implements PaymentServiceInterface
{
    /**
     * Create customer payment
     */
    public function createPayment(CreateCustomerPaymentDto $dto): CustomerPayment
    {
        return DB::transaction(function () use ($dto) {
            // Create payment
            $payment = $this->createPaymentRecord($dto);

            return $payment->refresh();
        });
    }

    /**
     * Create payment and apply to invoice atomically
     */
    public function createPaymentAndApplyToInvoice(CreateCustomerPaymentForInvoiceDto $dto): CustomerPayment
    {
        return DB::transaction(function () use ($dto) {
            // Create the payment first
            $payment = $this->createPayment(new CreateCustomerPaymentDto($dto->toArray()));

            // Apply payment to invoice
            $this->createPaymentApplication(new CreateApplyForInvoiceDto([
                'invoice_id' => $dto->invoice_id,
                'amount_applied' => $dto->amount,
                'apply_date' => $dto->payment_date,
                'applicable' => $payment,
                'applicable_type' => MorphablesEnum::PAYMENT->value,
            ]));

            return $payment->refresh();
        });
    }

    public function createPaymentAndApplyToInvoiceInstallmentPeriod(int $installmentPeriodId): CustomerPayment
    {
        $installmentPeriod = PaymentInstallmentPeriod::findOrFail($installmentPeriodId);

        return static::createPaymentAndApplyToInvoice(new CreateCustomerPaymentForInvoiceDto([
            'invoice_id' => $installmentPeriod->invoice_id,
            'amount' => $installmentPeriod->due_amount ?? $installmentPeriod->amount,
            'payment_date' => now(),
            'customer_id' => $installmentPeriod->invoice->customer_id,
        ]));
    }

    /**
     * Apply existing payment to invoice
     */
    public function applyPaymentToInvoice(CreateApplyForInvoiceDto $data): InvoiceApply
    {
        return DB::transaction(function () use ($data) {
            // Create application record
            $applyment = $this->createPaymentApplication($data);

            return $applyment;
        });
    }

    /**
     * Apply existing payment to invoice
     */
    public function applyPaymentToInvoices(CreateAppliesForMultipleInvoiceDto $data): Collection
    {
        return DB::transaction(function () use ($data) {
            // Create applications records
            $applies = $this->createPaymentApplicationForManyInvoices($data);

            return $applies;
        });
    }

    /**
     * Get available payments for application
     */
    public function getAvailablePayments(?Customer $customer = null): Collection
    {
        $query = CustomerPayment::query()
            ->where('amount_left', '>', 0);

        if ($customer) {
            $query->where('customer_id', $customer->id);
        }

        return $query->get();
    }

    /**
     * Calculate payment amount left
     */
    public function calculateAmountLeft(CustomerPayment $payment): SafeDecimal
    {
        if (!$payment->id) {
            Log::error('Trying to calculate due amount for unsaved payment', [
                'payment_data' => $payment->toArray()
            ]);

            // TODO: We could put a placeholder calculation here
            return new SafeDecimal('0.00');
        }

        // Use database function for accuracy and consistency
        return new SafeDecimal($payment->sql_amount_left ?? '0.00');
    }

    /**
     * Get payment applications
     */
    public function getPaymentApplications(CustomerPayment $payment): Collection
    {
        return InvoicePaymentModel::where('applicable_id', $payment->id)
            ->where('applicable_type', MorphablesEnum::PAYMENT->value)
            ->get();
    }

    /* PROTECTED METHODS - Can be overridden for customization */

    /**
     * Create payment record
     */
    protected function createPaymentRecord(CreateCustomerPaymentDto $dto): CustomerPayment
    {
        $payment = new CustomerPayment();
        $payment->customer_id = $dto->customer_id;
        $payment->payment_date = $dto->payment_date;
        $payment->amount = $dto->amount;
        $payment->payment_trace_id = $dto->payment_trace_id;
        $payment->save();

        return $payment;
    }

    /**
     * Create payment application record
     */
    protected function createPaymentApplication(CreateApplyForInvoiceDto $data): InvoiceApply
    {
        $invoicePayment = new InvoiceApply();
        $invoicePayment->invoice_id = $data->invoice_id;
        $invoicePayment->payment_applied_amount = $data->amount_applied;
        $invoicePayment->apply_date = $data->apply_date;
        $invoicePayment->applicable_id = $data->applicable->id;
        $invoicePayment->applicable_type = $data->applicable_type;
        $invoicePayment->save();

        $invoice = Invoice::findOrFail($data->invoice_id);

        if ($invoice->paymentTerm->consideredAsInitialPaid($invoice)) {
            $invoice->onConsideredAsInitialPaid();
        }

        if ($invoice->invoice_due_amount->equals(0)) {
            $invoice->onCompletePayment();
        } else {
            $invoice->onPartialPayment();
        }

        return $invoicePayment->refresh();
    }

    /**
     * Create payment application record
     */
    protected function createPaymentApplicationForManyInvoices(CreateAppliesForMultipleInvoiceDto $data): Collection
    {
        $invoices = $data->amounts_to_apply;

        $paymentsCreated = [];

        foreach ($invoices as $invoice) {
            $paymentsCreated[] = $this->createPaymentApplication(new CreateApplyForInvoiceDto([
                'invoice_id' => $invoice['id'],
                'amount_applied' => $invoice['amount_applied'],
                'apply_date' => $data->apply_date,
                'applicable' => $data->applicable,
                'applicable_type' => $data->applicable_type,
            ]));
        }

        return collect($paymentsCreated);
    }
}
