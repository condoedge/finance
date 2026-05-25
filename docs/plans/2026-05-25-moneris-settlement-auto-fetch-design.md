# Moneris settlement auto-fetch — design

**Date:** 2026-05-25
**Status:** Design approved, ready for implementation plan
**Branch:** `feat/processesment-fees-and-expenses`

## Problem

`ImportSettlementReportCommand` (`finance:import-settlement {provider} {file}`)
reads a Moneris settlement CSV and writes `processor_fees` onto
`customer_payments`. Today the command requires a CSV already sitting on disk
and is not on any schedule. Nothing in the codebase retrieves the daily report
from Moneris. The pipeline is manual.

Goal: after one-time configuration, the daily Moneris settlement report is
fetched, persisted, and imported with zero human action.

## Constraints

- Multi-tenant: Moneris credentials are per-team (`ProviderCredentials`); each
  team has its own `store_id` and (in production) its own Merchant Direct
  account.
- The existing `finance:import-settlement` command must keep working unchanged
  for emergencies and back-fill.
- Failure of one team must not block the others.
- The Moneris CSV column mapping in `config/finance-settlement.php` is still a
  placeholder; the fetch design must not depend on the mapping being final.

## Channel decision: Merchant Direct SFTP

Moneris's documented automated delivery channel for settlement reports is SFTP
via Merchant Direct. Each merchant account is provisioned with SFTP
credentials and the host fingerprint is published to merchants. Reports are
dropped daily as CSV.

Reporting API and IMAP/email were considered and rejected — API access is not
universal, and email is fragile (DMARC, attachment size, mailbox quotas).

## Architecture

Two commands, one cron, one ledger table.

```
                +---------------------------+
                | Moneris Merchant Direct   |   (daily CSV ~ 03:00 ET,
                | SFTP server (per team)    |    SPxxxxxx_yyyymmdd.csv)
                +-------------+-------------+
                              |
                              | scheduled pull (06:00 daily)
                              v
+-----------------------------+------------------------------+
| finance:fetch-settlements [--provider=moneris]             |
| For each team with Moneris enabled:                        |
|   1. Resolve SFTP creds (team override -> env default)     |
|   2. Connect via Flysystem SFTP v3 (host-key pinned)       |
|   3. List remote dir, skip files already in ledger         |
|   4. Stream new files to local "settlement-reports" disk   |
|   5. Insert fin_settlement_files row (idempotency)         |
|   6. Call SettlementImportService for each new file        |
+-----------------------------+------------------------------+
                              |
                              v
                +-------------+-------------+
                | SettlementImportService   |
                | writes processor_fees     |
                +---------------------------+
```

`finance:fetch-settlements` is new. `finance:import-settlement` stays as the
manual escape hatch. They are split because fetch and import fail for
different reasons (network vs. data) and we want independent retries.

## Credentials

Hybrid: per-team override + env-level default.

Extend `ProviderCredentials` for `moneris` with four new fields in
`ProviderCredentialsFormModal`:

| Field | Required | Notes |
|---|---|---|
| `sftp_host` | yes | e.g. `reports.moneris.com` |
| `sftp_username` | yes | Merchant Direct SFTP login |
| `sftp_private_key` | yes | PEM, encrypted at rest |
| `sftp_remote_path` | no | defaults to `/` |
| `sftp_host_fingerprint` | yes | SHA256, prevents MITM |

Resolution order per team at fetch time:
1. Team's `ProviderCredentials` row — use if all required fields set.
2. `config('finance-settlement.providers.moneris.sftp')`.
3. Skip team, log warning.

Env defaults:

```php
'sftp' => [
    'host'             => env('MONERIS_SFTP_HOST'),
    'username'         => env('MONERIS_SFTP_USERNAME'),
    'private_key'      => env('MONERIS_SFTP_PRIVATE_KEY_PATH'),
    'remote_path'      => env('MONERIS_SFTP_REMOTE_PATH', '/'),
    'host_fingerprint' => env('MONERIS_SFTP_HOST_FINGERPRINT'),
    'port'             => env('MONERIS_SFTP_PORT', 22),
],
```

Auth is SSH private key only — Moneris Merchant Direct does not accept
passwords. Host fingerprint is mandatory; without it the connector refuses to
connect (Flysystem `SftpConnectionProvider::hostFingerprint`).

## Fetch behavior

Command signature:

```
php artisan finance:fetch-settlements
    [--provider=moneris]
    [--team=ID]
    [--since=YYYY-MM-DD]
    [--dry-run]
```

No-args run iterates every team with `moneris` enabled in
`TeamPaymentProvider`. Flags are for ops/back-fill.

Per-team loop:

```
1. Build SftpConnectionProvider from resolved creds + host fingerprint.
2. Wrap as a Flysystem disk on-the-fly (not a global config disk).
3. List remote_path, filter to *.csv newer than 30 days
   (configurable; safety net for missed days).
4. For each remote file:
   a. Lookup fin_settlement_files by (team_id,'moneris',filename).
   b. Row exists and imported_at IS NOT NULL  -> skip.
   c. Row exists and imported_at IS NULL      -> re-attempt import only.
   d. No row                                  -> stream-download to local
      "settlement-reports" disk at moneris/{team_id}/{filename}, verify
      size, compute sha256, insert row.
5. For each row needing import: call SettlementImportService directly
   (not via Artisan::call). Persist result JSON + imported_at.
6. Do not delete remote files. Moneris keeps history; deletion requires
   explicit user request.
```

## Idempotency ledger: `fin_settlement_files`

| col | type | notes |
|---|---|---|
| `id` | bigint pk | |
| `team_id` | unsignedBigInt | indexed |
| `provider_code` | string(32) | |
| `remote_filename` | string(255) | |
| `local_path` | string(512) | on `settlement-reports` disk |
| `remote_size` | unsignedInt | |
| `sha256` | char(64) | content hash |
| `fetched_at` | timestamp | |
| `imported_at` | timestamp nullable | null = pending/failed |
| `import_result_json` | json nullable | row count, unmatched refs |
| `last_error` | text nullable | latest failure message |

Unique indexes:
- `(team_id, provider_code, remote_filename)` — dedupe by name.
- `(team_id, provider_code, sha256)` — dedupe by content, so a renamed but
  identical file does not double-import.

## Error handling

Log-only, per product decision.

- Per-team try/catch. One broken team never blocks the others.
- Every exception logs to `Log::channel('settlement')` and writes
  `last_error` on the ledger row.
- Command exit code is `SUCCESS` if at least one team made progress,
  `FAILURE` if every team failed. Cron output thus highlights total
  outages but tolerates per-tenant noise.

## Wiring

`CondoedgeFinanceServiceProvider.php`:

```php
// loadCommands()
\Condoedge\Finance\Command\FetchSettlementReportsCommand::class,

// setCronExecutions()
$schedule->command('finance:fetch-settlements')
    ->dailyAt('06:00')                 // Moneris drops ~03:00 ET
    ->withoutOverlapping(120)          // 2 h lock
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/moneris-settlement.log'));
```

`runInBackground` prevents a hung SFTP socket from freezing the scheduler;
`withoutOverlapping` still prevents two concurrent runs.

## File layout

```
src/Billing/Settlement/Fetch/
  SettlementFetcherInterface.php       -- per-provider strategy
  MonerisSftpSettlementFetcher.php     -- the SFTP impl
  SettlementFetchService.php           -- orchestrates per-team loop
src/Command/
  FetchSettlementReportsCommand.php
src/Models/
  SettlementFile.php                   -- maps fin_settlement_files
database/migrations/
  2026_05_25_000001_create_fin_settlement_files_table.php
config/finance-settlement.php          -- extend with 'sftp' + 'fetch'
```

`SettlementFetcherInterface` leaves the door open for a future BNA / Stripe /
IMAP fetcher without rewriting the command.

## Testing

- **Unit:** `MonerisSftpSettlementFetcher` with a Flysystem in-memory adapter
  and a fake `SftpConnectionProvider`. Assert filename filter, mandatory
  host fingerprint, hash computation.
- **Service:** `SettlementFetchService` with a stubbed fetcher and a real DB.
  Assert idempotency (run twice → one row, one import), per-team isolation
  (team A throws → team B still imports), result-JSON persistence.
- **Command:** thin smoke test — exit code `SUCCESS` when at least one team
  progressed, `FAILURE` otherwise.
- **No live-Moneris CI test.** Document a manual smoke procedure with a
  Moneris test Merchant Direct account.

## Dependencies

- `league/flysystem-sftp-v3` — add to `composer.json` `require` explicitly.
  It is a transitive dep of Laravel today via `suggest`, but we should not
  rely on that.

## Ops surface

- `finance:fetch-settlements --dry-run` — connect, list, report, change
  nothing.
- `finance:fetch-settlements --team=42 --since=2026-05-01` — back-fill.
- `finance:import-settlement` — unchanged emergency / manual path.

## Documentation

One short section appended to `README.md`:
- Where SFTP creds go (UI vs `.env`).
- How to obtain the host fingerprint from Moneris:
  `ssh-keyscan -t rsa reports.moneris.com | ssh-keygen -lf - -E sha256`.
- The `settlement-reports` disk users must add to `config/filesystems.php`.

## What this design promises

After one-time configuration:

1. Daily cron at 06:00, zero human touch.
2. Per-tenant isolation — one bad team does not block others.
3. Idempotent — running twice is safe; missed days self-heal via 30-day
   look-back.
4. Host-key pinned — will not silently break or be MITM'd.
5. Fetch split from import — retries are cheap.

## What still requires the user (one-time)

- Request SFTP access + host fingerprint from Moneris for each merchant.
- Enter creds in the settings UI (or `.env` for the shared default).
- Confirm the real CSV column names against an actual settlement file —
  current values in `config/finance-settlement.php` are placeholders and the
  fee column is the load-bearing one.
