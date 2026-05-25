<?php

namespace Condoedge\Finance\Billing\Settlement;

use Condoedge\Finance\Billing\Settlement\Contracts\SettlementReportParserInterface;
use Illuminate\Support\Collection;

/**
 * Config-driven CSV parser — works for any provider whose settlement report is
 * a CSV, given the column mapping in config/finance-settlement.php.
 */
class CsvSettlementReportParser implements SettlementReportParserInterface
{
    public function parse(string $filePath, array $config): Collection
    {
        if (!is_readable($filePath)) {
            throw new \RuntimeException("Settlement file not readable: {$filePath}");
        }

        $columns = $config['columns'] ?? [];
        $delimiter = $config['delimiter'] ?? ',';
        $hasHeader = $config['has_header'] ?? true;

        $rows = collect();
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Could not open settlement file: {$filePath}");
        }

        try {
            $headerMap = null;

            while (($record = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($hasHeader && $headerMap === null) {
                    $headerMap = array_flip(array_map('trim', $record));
                    continue;
                }

                $row = $this->mapRow($record, $columns, $headerMap);
                if ($row !== null) {
                    $rows->push($row);
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Map one CSV record to a SettlementRow, or null when it lacks a ref/fee.
     */
    protected function mapRow(array $record, array $columns, ?array $headerMap): ?SettlementRow
    {
        $get = function (string $key) use ($record, $columns, $headerMap): ?string {
            $col = $columns[$key] ?? null;
            if ($col === null) {
                return null;
            }

            // Columns are referenced by header name, or by numeric index when headerless.
            $index = $headerMap !== null ? ($headerMap[$col] ?? null) : (is_numeric($col) ? (int) $col : null);

            return ($index !== null && array_key_exists($index, $record)) ? trim((string) $record[$index]) : null;
        };

        $ref = $get('transaction_ref');
        $fee = $get('fee');

        if ($ref === null || $ref === '' || $fee === null || $fee === '') {
            return null;
        }

        $gross = $get('gross');

        return new SettlementRow(
            transactionRef: $ref,
            fee: $this->toAmount($fee),
            gross: ($gross !== null && $gross !== '') ? $this->toAmount($gross) : null,
        );
    }

    /**
     * Normalize a money string ("1,234.56", "$1.20", "(2.00)") to a float.
     */
    protected function toAmount(string $value): float
    {
        $value = trim($value);
        // Accounting CSVs wrap negatives in parentheses, e.g. "(2.00)".
        $negative = (bool) preg_match('/^\(.*\)$/', $value);
        $clean = (float) preg_replace('/[^0-9.\-]/', '', $value);

        return $negative ? -abs($clean) : $clean;
    }
}
