<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\InvoiceStatusEnum;
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

        $this->info('Integrity check completed successfully!');
    }
}
