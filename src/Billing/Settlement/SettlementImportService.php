<?php

namespace Condoedge\Finance\Billing\Settlement;

use Condoedge\Finance\Billing\Settlement\Contracts\SettlementImportServiceInterface;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\PaymentTrace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Imports a provider settlement report: parses it, matches each row to a
 * payment by its transaction reference, and records the real processor fee.
 */
class SettlementImportService implements SettlementImportServiceInterface
{
    public function import(string $providerCode, string $filePath): SettlementImportResult
    {
        $config = config("finance-settlement.providers.{$providerCode}");

        if (!$config) {
            throw new \InvalidArgumentException("No settlement config for provider '{$providerCode}'.");
        }

        $parser = app($config['parser'] ?? CsvSettlementReportParser::class);
        $rows = $parser->parse($filePath, $config);

        $matched = 0;
        $unmatchedRefs = [];

        DB::transaction(function () use ($rows, $providerCode, &$matched, &$unmatchedRefs) {
            foreach ($rows as $row) {
                $payment = $this->findPayment($providerCode, $row->transactionRef);

                if (!$payment) {
                    $unmatchedRefs[] = $row->transactionRef;
                    continue;
                }

                // Saving recomputes the calculated `net` column via the integrity system.
                $payment->processor_fees = $row->fee;
                $payment->save();
                $matched++;
            }
        });

        $result = new SettlementImportResult(
            providerCode: $providerCode,
            rowsParsed: $rows->count(),
            matched: $matched,
            unmatched: count($unmatchedRefs),
            unmatchedRefs: array_slice($unmatchedRefs, 0, 50),
        );

        Log::info('Settlement import completed', (array) $result);

        return $result;
    }

    /**
     * A payment is matched through its payment trace's external transaction ref.
     */
    protected function findPayment(string $providerCode, string $transactionRef): ?CustomerPayment
    {
        $trace = PaymentTrace::query()
            ->where('payment_provider_code', $providerCode)
            ->where('external_transaction_ref', $transactionRef)
            ->latest('id')
            ->first();

        return $trace
            ? CustomerPayment::where('payment_trace_id', $trace->id)->first()
            : null;
    }
}
