<?php

namespace Condoedge\Finance\Services\PaymentTerm;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\Dto\PaymentTerms\CreateOrUpdatePaymentTermDto;
use Condoedge\Finance\Models\Dto\PaymentTerms\CreatePaymentInstallmentPeriodsDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentInstallmentPeriod;
use Condoedge\Finance\Models\PaymentInstallPeriodStatusEnum;
use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\PaymentTermTypeEnum;

class PaymentTermService implements PaymentTermServiceInterface
{
    public function createOrUpdatePaymentTerm(CreateOrUpdatePaymentTermDto $dto): PaymentTerm
    {
        if (isset($dto->id)) {
            $paymentTerm = PaymentTerm::findOrFail($dto->id);
        } else {
            $paymentTerm = new PaymentTerm();
        }

        $paymentTerm->term_name = $dto->term_name;
        $paymentTerm->term_description = $dto->term_description;
        $paymentTerm->term_type = $dto->term_type->value;
        $paymentTerm->settings = $dto->settings;
        $paymentTerm->save();

        return $paymentTerm;
    }

    public function manageNewPaymentTermIntoInvoice(Invoice $invoice, PaymentTermTypeEnum $oldPaymentTermType = null)
    {
        if ($oldPaymentTermType) {
            $oldPaymentTermType->manageOldPaymentTermIntoInvoice($invoice);
        }

        $paymentTerm = $invoice->paymentTerm;

        if (!$paymentTerm) {
            return;
        }

        $invoice->invoice_due_date = $paymentTerm->term_type->calculateDueDate($invoice->invoice_date, $paymentTerm->settings);
        $invoice->save();

        $paymentTerm->term_type->manageNewPaymentTermIntoInvoice($invoice, $paymentTerm->settings);
    }

    public function createPaymentInstallmentPeriods(CreatePaymentInstallmentPeriodsDto $dto)
    {
        $invoice = InvoiceModel::findOrFail($dto->invoice_id);
        $installmentPeriods = [];
        $price = $invoice->invoice_total_amount;

        $dividedPrice = $price->preciseDivide($dto->periods);
        $roundedUp = $dividedPrice['first_division'];
        $roundedDown = $dividedPrice['remaining_values'];

        for ($i = 0; $i < $dto->periods; $i++) {
            $amount = $i === 0 ? $roundedUp : $roundedDown;
            $installmentPeriods[] = [
                'installment_number' => $i + 1,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'due_amount' => $amount,
                'due_date' => $invoice->invoice_date->add($dto->interval_type, $dto->interval * $i),
                'updated_at' => now(),
                'created_at' => now(),
                'status' => PaymentInstallPeriodStatusEnum::PENDING->value,
            ];
        }

        if (!$dto->dry_run) {
            PaymentInstallmentPeriod::insert($installmentPeriods);
        }

        return $installmentPeriods;
    }
}
