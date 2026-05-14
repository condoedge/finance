<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Providers\Moneris\MonerisPaymentProvider;
use Condoedge\Finance\Facades\PaymentProcessor;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTrace;
use Condoedge\Finance\Models\PaymentTraceStatusEnum;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;

/**
 * Reconcile Moneris hosted-checkout payments left in PROCESSING.
 *
 * Why this exists: Moneris MCO doesn't have reliable IPN/webhook delivery. The
 * authoritative confirmation is the redirect-back to MonerisReturnController,
 * which calls /receipt. If the user closes the browser between paying on
 * Moneris's hosted page and our return URL firing, the PaymentTrace row stays
 * in PROCESSING forever and the customer gets charged without their invoice
 * being marked paid.
 *
 * This command sweeps Moneris traces older than --min-age-minutes (default 5)
 * still in PROCESSING, polls /receipt for each ticket, and re-enters the
 * normal processPayment flow so success/failure gets recorded the same way
 * the return-back path records it. Idempotent: re-running on an already-
 * processed ticket is a no-op (PaymentTrace status is COMPLETED so the query
 * skips it).
 *
 * Scheduled every 15 minutes from CondoedgeFinanceServiceProvider.
 */
class ReconcileMonerisPaymentsCommand extends Command
{
    protected $signature = 'finance:reconcile-moneris
                            {--min-age-minutes=5 : Skip traces newer than this; gives the redirect-back path time to fire first}
                            {--max-age-hours=24 : Stop reconciling after this; older tickets are abandoned}
                            {--limit=100 : Max traces per run}
                            {--dry-run : Report only}';

    protected $description = 'Reconcile Moneris hosted-checkout payments stuck in PROCESSING (browser closed before redirect-back)';

    public function handle(): int
    {
        $minAge = (int) $this->option('min-age-minutes');
        $maxAge = (int) $this->option('max-age-hours');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $now = now();
        $traces = PaymentTrace::query()
            ->where('payment_provider_code', 'moneris')
            ->where('status', PaymentTraceStatusEnum::PROCESSING)
            ->where('created_at', '<=', $now->copy()->subMinutes($minAge))
            ->where('created_at', '>=', $now->copy()->subHours($maxAge))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($traces->isEmpty()) {
            $this->info('No Moneris traces to reconcile.');
            return Command::SUCCESS;
        }

        $this->info("Reconciling {$traces->count()} Moneris trace(s)...");

        $provider = app(MonerisPaymentProvider::class);
        $confirmed = 0;
        $declined = 0;
        $errored = 0;

        foreach ($traces as $trace) {
            $ticket = $trace->external_transaction_ref;
            $this->line(" - ticket={$ticket} payable={$trace->payable_type}#{$trace->payable_id}");

            if ($dryRun) {
                continue;
            }

            try {
                $payable = $this->loadPayable($trace->payable_type, (int) $trace->payable_id);
                if (!$payable) {
                    Log::warning('Moneris reconciliation: payable missing', [
                        'ticket' => $ticket,
                        'payable_type' => $trace->payable_type,
                        'payable_id' => $trace->payable_id,
                    ]);
                    $errored++;
                    continue;
                }

                $context = new PaymentContext(
                    payable: $payable,
                    paymentMethod: $trace->payment_method_id instanceof PaymentMethodEnum
                        ? $trace->payment_method_id
                        : PaymentMethodEnum::from((int) $trace->payment_method_id),
                    paymentData: ['ticket' => $ticket],
                );

                $result = $provider->processPayment($context);
                PaymentProcessor::managePaymentResult($result, $context);

                if ($result->success) {
                    $confirmed++;
                } else {
                    $declined++;
                }
            } catch (\Throwable $e) {
                Log::error('Moneris reconciliation failed for ticket', [
                    'ticket' => $ticket,
                    'error' => $e->getMessage(),
                ]);
                $errored++;
            }
        }

        $this->info("Done. confirmed={$confirmed} declined={$declined} errored={$errored}");

        return Command::SUCCESS;
    }

    private function loadPayable(?string $type, int $id)
    {
        if (!$type) {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?: $type;

        if (!class_exists($class)) {
            return null;
        }

        return $class::find($id);
    }
}
