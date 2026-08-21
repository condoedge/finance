<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\PaymentTermTypeEnum;
use Illuminate\Console\Command;

class EnsureInvoiceEventsAreProcessed extends Command
{
    public $signature = 'finance:ensure-invoice-events-processed';

    public $description = 'Ensure invoice events were processed on status changes';

    public function handle()
    {
        $this->info('Running invoice events after payments...');

        $failures = 0;

        InvoiceModel::whereNull('complete_payment_managed_at')
            ->where('invoice_status_id', InvoiceStatusEnum::PAID)
            ->chunkById(100, function ($invoices) use (&$failures) {
                foreach ($invoices as $invoice) {
                    $this->process($invoice, 'complete_payment_managed_at', fn () => $invoice->onCompletePayment(), $failures);
                }
            });

        InvoiceModel::whereNull('partial_payment_managed_at')
            ->where('invoice_status_id', InvoiceStatusEnum::PARTIAL)
            ->chunkById(100, function ($invoices) use (&$failures) {
                foreach ($invoices as $invoice) {
                    $this->process($invoice, 'partial_payment_managed_at', fn () => $invoice->onPartialPayment(), $failures);
                }
            });

        InvoiceModel::whereNull('overdue_managed_at')
            ->where('invoice_status_id', InvoiceStatusEnum::OVERDUE)
            ->chunkById(100, function ($invoices) use (&$failures) {
                foreach ($invoices as $invoice) {
                    $this->process($invoice, 'overdue_managed_at', fn () => $invoice->onOverdue(), $failures);
                }
            });

        // Drafts are excluded on purpose. The three blocks above filter on a concrete status,
        // which a draft never holds; this one leans on the term scope, and NET adds no
        // condition at all — so without this every unissued invoice was swept in.
        $query = InvoiceModel::whereNull('considered_as_initial_paid_at')
            ->where('is_draft', false);

        collect(PaymentTermTypeEnum::cases())->each(function ($paymentTerm) use ($query, &$failures) {
            $this->info("Processing invoices for payment term: {$paymentTerm->label()}");

            $query = clone $query;

            $query = $query->whereHas('paymentTerm', function ($q) use ($paymentTerm) {
                $q->where('term_type', $paymentTerm->value);
            });

            $query = PaymentTerm::scopeConsideredAsInitialPaid($query, $paymentTerm);

            $query->chunkById(100, function ($invoices) use (&$failures) {
                foreach ($invoices as $invoice) {
                    $this->process($invoice, 'considered_as_initial_paid_at', fn () => $invoice->onConsideredAsInitialPaid(), $failures);
                }
            });
        });

        if ($failures) {
            $this->error("{$failures} invoice hook(s) failed this run. The exception message is in the application log.");

            return self::FAILURE;
        }

        $this->info('Integrity check completed successfully!');

        return self::SUCCESS;
    }

    protected function process($invoice, string $markerColumn, callable $hook, int &$failures): void
    {
        $this->info("Processing invoice ID: {$invoice->id}");

        $hook();

        // Without global scopes: this is a read-back of a column we just wrote, and a scope
        // filtering the row out would read as a failure that never happened.
        if ($invoice->newQueryWithoutScopes()->whereKey($invoice->id)->value($markerColumn)) {
            $this->info("Invoice ID: {$invoice->id} processed successfully.");

            return;
        }

        $failures++;
        $this->error("Invoice ID: {$invoice->id} hook FAILED ({$markerColumn} not set) - see the application log for the exception message.");
    }
}
