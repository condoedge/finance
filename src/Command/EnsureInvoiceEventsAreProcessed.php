<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\PaymentTermTypeEnum;
use Illuminate\Console\Command;

class EnsureInvoiceEventsAreProcessed extends Command
{
    public $signature = 'finance:ensure-invoice-events-processed';

    public $description = 'Ensure invoice events were processed on status changes';

    public function handle()
    {
        $this->info('Running invoice events after payments...');

        InvoiceModel::whereNull('complete_payment_managed_at')
            ->where('status', InvoiceStatusEnum::PAID)
            ->chunk(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->info("Processing invoice ID: {$invoice->id}");

                    $invoice->onCompletePayment();

                    $this->info("Invoice ID: {$invoice->id} processed successfully.");
                }
            });

        InvoiceModel::whereNull('partial_payment_managed_at')
            ->where('status', InvoiceStatusEnum::PARTIAL)
            ->chunk(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->info("Processing invoice ID: {$invoice->id}");

                    $invoice->onPartialPayment();

                    $this->info("Invoice ID: {$invoice->id} processed successfully.");
                }
            });

        InvoiceModel::whereNull('overdue_managed_at')
            ->where('status', InvoiceStatusEnum::OVERDUE)
            ->chunk(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->info("Processing invoice ID: {$invoice->id}");

                    $invoice->onOverdue();

                    $this->info("Invoice ID: {$invoice->id} processed successfully.");
                }
            });

        $query = InvoiceModel::whereNull('considered_as_initial_paid_managed_at');

        collect(PaymentTermTypeEnum::cases())->each(function ($paymentTerm) use ($query) {
            $this->info("Processing invoices for payment term: {$paymentTerm->label()}");

            $query = clone $query;

            $query->whereHas('paymentTerm', function ($q) use ($paymentTerm) {
                $paymentTerm->consideredAsInitialPaidScope($q->where('term_type', $paymentTerm->value));
            });

            $query->chunk(100, function ($invoices) use ($paymentTerm) {
                foreach ($invoices as $invoice) {
                    $this->info("Processing invoice ID: {$invoice->id} for payment term: {$paymentTerm->label()}");

                    $invoice->onConsideredAsInitialPaid();

                    $this->info("Invoice ID: {$invoice->id} processed successfully for payment term: {$paymentTerm->label()}");
                }
            });
        });
            
            
        $this->info('Integrity check completed successfully!');
    }
}
