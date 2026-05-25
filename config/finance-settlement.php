<?php

/*
 * Per-provider settlement-report import + fetch configuration.
 *
 * Some payment providers (e.g. Moneris) never return the processing fee in the
 * transaction response — the fee is only known after settlement, in a report.
 * The fetcher pulls that report from the provider's SFTP / API, and the
 * importer reads it and writes processor_fees onto each payment.
 *
 * Each provider entry maps the importer's logical fields to the report's
 * actual columns. Per-team SFTP credentials override the env defaults below.
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

            /*
             * SFTP defaults. Per-team values in ProviderCredentials override
             * these. Host fingerprint is mandatory — without it the connector
             * refuses to open the session.
             */
            'sftp' => [
                'host' => env('MONERIS_SFTP_HOST'),
                'username' => env('MONERIS_SFTP_USERNAME'),
                'private_key_path' => env('MONERIS_SFTP_PRIVATE_KEY_PATH'),
                'private_key_passphrase' => env('MONERIS_SFTP_PRIVATE_KEY_PASSPHRASE'),
                'host_fingerprint' => env('MONERIS_SFTP_HOST_FINGERPRINT'),
                'remote_path' => env('MONERIS_SFTP_REMOTE_PATH', '/'),
                'port' => (int) env('MONERIS_SFTP_PORT', 22),
            ],
        ],

    ],

    /*
     * Fetcher tunables. local_disk must exist in config/filesystems.php on
     * the consuming app — see README for the example entry.
     */
    'fetch' => [
        'local_disk' => env('SETTLEMENT_LOCAL_DISK', 'settlement-reports'),
        'lookback_days' => (int) env('SETTLEMENT_LOOKBACK_DAYS', 30),
        'log_channel' => env('SETTLEMENT_LOG_CHANNEL', 'stack'),
    ],
];
