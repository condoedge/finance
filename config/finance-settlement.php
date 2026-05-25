<?php

/*
 * Per-provider settlement-report import configuration.
 *
 * Some payment providers (e.g. Moneris) never return the processing fee in the
 * transaction response — the fee is only known after settlement, in a report.
 * The importer reads that report and writes processor_fees onto each payment.
 *
 * Each provider entry maps the importer's logical fields to the report's actual
 * columns. Swap `parser` for a non-CSV provider, or override this whole file in
 * the app once the real report format is confirmed.
 */
return [
    'providers' => [

        /*
         * PLACEHOLDER — the column names below are best guesses. Confirm them
         * against a real Moneris Merchant Direct fee/settlement CSV export;
         * once corrected, this provider works with no code change.
         */
        'moneris' => [
            'parser' => \Condoedge\Finance\Billing\Settlement\CsvSettlementReportParser::class,
            'has_header' => true,
            'delimiter' => ',',
            'columns' => [
                'transaction_ref' => 'Transaction Number',
                'fee' => 'Fee Amount',
                'gross' => 'Transaction Amount',
            ],
        ],

    ],
];
