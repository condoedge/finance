# Dynamic Payment Provider System — Design

**Date:** 2026-05-13
**Companion audit:** `2026-05-13-finance-audit.md` (§1 Provider findings, §2 Seam map)
**Status:** Draft for review before implementation

---

## 1. Goals & Non-Goals

### Goals

1. **Per-team provider selection.** Each team chooses which provider handles each payment method (e.g., Team A uses Moneris for credit cards, Team B uses Stripe).
2. **Ordered fallback chain.** A team can configure 1..N providers per method, with priority. If the highest-priority provider fails, the system tries the next one.
3. **Single-provider mode.** A team that wants exactly one provider per method (no automatic fallback) gets that explicit behavior; failures surface immediately.
4. **Pre-form availability check.** Before rendering the payment form, verify at least one configured provider is healthy. If none are, show a friendly notice instead of an error mid-payment.
5. **Health tracking.** Recent transaction outcomes per provider drive an "is this provider healthy?" signal that gates form rendering and fallback decisions.
6. **Structured logging.** Every payment attempt logs `{team_id, payable, provider_code, action, outcome, latency, reason}` so we can answer "which provider is failing for which team this week?".
7. **Moneris provider.** New provider supporting Moneris Hosted Checkout (a redirect-out flow), demonstrating that the contract accommodates non-inline flows.

### Non-Goals

- **Not** rewriting `PaymentMethodEnum` — payment methods stay enum-driven (Credit Card, ACH, Interac, etc.). What changes is the *mapping* from method to provider.
- **Not** designing per-customer provider routing (e.g., "use provider X for premium customers"). Out of scope; can layer on later by adding `customer_segment` to the lookup table if ever needed.
- **Not** A/B testing or load balancing across providers. Fallback is failure-driven only.
- **Not** a full circuit-breaker library (Hystrix-style). A simple sliding-window failure count is sufficient.

---

## 2. Data Model

### 2.1 New table — `fin_team_payment_providers`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigInt PK | |
| `team_id` | bigInt FK → `teams.id` | Indexed |
| `payment_method_id` | smallInt | Matches `PaymentMethodEnum` values |
| `provider_code` | varchar(32) | Matches `PaymentGatewayInterface::getCode()` — e.g. `'stripe'`, `'bna'`, `'moneris'` |
| `priority` | smallInt | Lower = higher priority. `1` = primary. |
| `is_active` | bool | Soft-disable without deleting |
| `credentials_id` | bigInt FK → `fin_provider_credentials.id` nullable | Per-team credentials |
| `mode` | enum('single', 'fallback') | Per-method behavior |
| `created_at`, `updated_at` | timestamps | |

**Indexes:**
- `(team_id, payment_method_id, priority)` — resolver lookup
- `(team_id, payment_method_id, is_active)` — filtered lookup
- Unique `(team_id, payment_method_id, provider_code)` — one row per provider per method per team

**Why `mode` per row, not per team:** lets a team have fallback for credit cards but single-mode for bank transfers. Default = `single`.

### 2.2 New table — `fin_provider_credentials`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigInt PK | |
| `team_id` | bigInt FK → `teams.id` nullable | Null = global default (from env) |
| `provider_code` | varchar(32) | |
| `credentials` | text | **Encrypted** via Laravel's `encrypted` cast (JSON blob — keys, secrets, store IDs) |
| `is_test` | bool | Test vs prod credentials |
| `last_rotated_at` | timestamp nullable | For audit |
| `created_at`, `updated_at` | timestamps | |

**Index:** `(team_id, provider_code, is_test)`.

**Why a separate table:** credentials may be rotated independently of the team's preferred provider order. Also gives one place to audit secret access (model events).

### 2.3 Extend `fin_payment_traces`

Add columns:
- `team_id` bigInt nullable indexed
- `failure_reason_code` varchar(64) nullable — provider-classified (`AUTH`, `RATE_LIMIT`, `NETWORK`, `CARD_DECLINED`, `TRANSIENT`, `PERMANENT`, ...)
- `latency_ms` int nullable
- `retry_count` smallInt default 0 — increments on each fallback attempt for the same payable

Add index: `(provider_code, team_id, created_at)` — for fast health queries.

### 2.4 Optional cache — `fin_provider_health_snapshots`

Cached materialized view of "is provider X healthy for team Y?". Avoids scanning `payment_traces` on every form render.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigInt PK | |
| `team_id` | bigInt nullable | Null = global health |
| `provider_code` | varchar(32) | |
| `status` | enum('healthy', 'degraded', 'down') | |
| `consecutive_failures` | smallInt | |
| `last_failure_at` | timestamp nullable | |
| `last_success_at` | timestamp nullable | |
| `updated_at` | timestamp | |

Unique `(team_id, provider_code)`. Updated by the health checker on every attempt; read on every form render (cheap).

If we'd rather not materialize, the checker can query `payment_traces` directly with a 5-minute Laravel cache.

---

## 3. Contract Changes

### 3.1 `PaymentGatewayInterface` — additions

```php
namespace Condoedge\Finance\Billing\Contracts;

interface PaymentGatewayInterface
{
    // Existing
    public function getCode(): string;
    public function getSupportedPaymentMethods(): array;
    public function getPaymentForm(PaymentContext $context): ?Element;
    public function charge(PaymentContext $context, array $data): PaymentResult;

    // New
    public function getCheckoutFlow(): PaymentFlowEnum;
    public function getHostedCheckoutUrl(PaymentContext $context): ?HostedCheckoutTicket;
    public function classifyError(\Throwable $e): ErrorClassification;
    public function withCredentials(?ProviderCredentials $creds): static;  // immutable copy with creds
}
```

A new `BasicGatewayTrait` provides default INLINE flow + naive error classification so existing Stripe and BNA providers need minimal changes:

```php
trait BasicGatewayTrait
{
    public function getCheckoutFlow(): PaymentFlowEnum
    {
        return PaymentFlowEnum::INLINE;
    }

    public function getHostedCheckoutUrl(PaymentContext $context): ?HostedCheckoutTicket
    {
        return null;
    }

    public function classifyError(\Throwable $e): ErrorClassification
    {
        return ErrorClassification::permanent($e->getMessage());
    }
}
```

### 3.2 New enums and value objects

```php
enum PaymentFlowEnum: string
{
    case INLINE = 'inline';                 // Form rendered in-page (Stripe Elements, BNA card form)
    case HOSTED_REDIRECT = 'hosted_redirect'; // GET redirect to provider (returns to our callback)
    case HOSTED_POST = 'hosted_post';       // POST form auto-submits to provider (Moneris MPG)
    case HOSTED_IFRAME = 'hosted_iframe';   // Provider page in iframe (future)
}

enum ErrorClassificationCategory: string
{
    case TRANSIENT = 'transient';   // Network blip, rate limit — retry candidate
    case AUTH = 'auth';             // Bad credentials — never retry, alert ops
    case PERMANENT = 'permanent';   // Card declined, validation — surface to user
    case UNKNOWN = 'unknown';
}

final class ErrorClassification
{
    public function __construct(
        public readonly ErrorClassificationCategory $category,
        public readonly string $reasonCode,
        public readonly bool $shouldRetry,
        public readonly bool $shouldFallback,
    ) {}
    // static factories: transient(), authFailure(), permanent(), unknown()
}

final class HostedCheckoutTicket
{
    public function __construct(
        public readonly string $url,            // Where to redirect/POST
        public readonly string $method,          // 'GET' or 'POST'
        public readonly array $fields = [],      // POST fields (for HOSTED_POST)
        public readonly ?string $ticketId = null, // Reference for reconciliation
        public readonly ?array $metadata = [],
    ) {}
}
```

### 3.3 `PaymentGatewayResolverInterface` — additions

```php
interface PaymentGatewayResolverInterface
{
    public function resolve(PaymentContext $context): PaymentGatewayInterface;  // First in chain

    /** @return iterable<PaymentGatewayInterface> ordered by priority, healthy first */
    public function resolveChain(PaymentContext $context): iterable;

    public function getAvailableGateways(PaymentContext $context): array;

    /** Returns the chain that *would* be attempted; useful for the pre-form check. */
    public function previewChain(PaymentContext $context): array;
}
```

### 3.4 New `ProviderHealthCheckerInterface`

```php
interface ProviderHealthCheckerInterface
{
    public function isHealthy(string $providerCode, ?int $teamId = null): bool;
    public function status(string $providerCode, ?int $teamId = null): ProviderHealthStatus;

    /** Called by PaymentProcessor after each attempt. */
    public function record(string $providerCode, ?int $teamId, PaymentOutcome $outcome): void;
}
```

`ProviderHealthStatus`: value object with `healthy|degraded|down`, `consecutive_failures`, `last_failure_at`. Materialized from `fin_provider_health_snapshots` (or computed from `payment_traces` if we skip the snapshot table).

### 3.5 `PaymentContext` — add `team_id`

```php
final class PaymentContext
{
    public function __construct(
        public readonly PayableInterface $payable,
        public readonly PaymentMethodEnum $paymentMethod,
        public readonly int $teamId,                      // NEW — resolved from $payable->team()
        public readonly array $metadata = [],
    ) {}
}
```

If `$payable->team_id` is null (legacy data), context falls back to `auth()->user()?->current_team_id`. If still null, resolver throws `NoTeamContextException`.

### 3.6 `PaymentResult` — `nextAction` field

```php
final class PaymentResult
{
    public function __construct(
        public readonly PaymentResultStatus $status,         // SUCCESS, PENDING, FAILED
        public readonly ?string $transactionId = null,
        public readonly ?float $amount = null,
        public readonly ?NextAction $nextAction = null,      // NEW
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = [],
    ) {}
}

final class NextAction
{
    public function __construct(
        public readonly NextActionType $type,    // REDIRECT, POST_FORM, MODAL, NONE
        public readonly ?string $url = null,
        public readonly array $payload = [],
    ) {}
}
```

### 3.7 New `NoProviderAvailableException`

Replaces the `abort(403)` in the current resolver and the generic `\Exception` from the registry. Caller decides whether to render a friendly view (Kompo UI) or 503 (API).

---

## 4. Resolver Behavior

### 4.1 `resolveChain()` algorithm

```
input: PaymentContext { team_id, payment_method }
1. rows := SELECT * FROM fin_team_payment_providers
            WHERE team_id = ? AND payment_method_id = ? AND is_active = 1
            ORDER BY priority ASC
2. If rows is empty:
   - Fallback to global config (config('kompo-finance.payment_method_providers')) for backwards compat.
   - If still empty: throw NoProviderAvailableException::noneConfigured($method, $teamId)
3. healthy := []
   degraded := []
   foreach row in rows:
     status := healthChecker->status(row.provider_code, team_id)
     case status.healthy: healthy[] := build(row)
     case status.degraded: degraded[] := build(row)
     case status.down: skip (still log "skipped down provider")
4. chain := healthy + degraded   // try healthy first, then degraded (degraded is last resort)
5. If chain is empty: throw NoProviderAvailableException::allDown($method, $teamId, rows)
6. If row.mode == 'single' for the primary row: return only [chain[0]]
   else: return chain
```

`build(row)`:
- Load `ProviderCredentials` for `(team_id, provider_code)`, falling back to global env credentials.
- `$provider = app($providerClass)->withCredentials($creds)`.

### 4.2 `PaymentProcessor::processPayment()` — fallback execution

```
foreach (resolver.resolveChain(context) as $provider) {
    $start := now()
    try {
        $result := $provider->charge(context, $data)
        record_trace(SUCCESS, latency)
        healthChecker->record(provider.code, team_id, SUCCESS)
        return $result
    } catch (\Throwable $e) {
        $cls := $provider->classifyError($e)
        record_trace(FAILED, latency, reason=$cls.reasonCode)
        healthChecker->record(provider.code, team_id, FAILED($cls.category))
        if (!$cls.shouldFallback) throw   // e.g. card declined — don't try next provider
    }
}
throw NoProviderAvailableException::allFailed(...)
```

**Critical rule:** card declined (PERMANENT) does NOT trigger fallback — the customer's card is bad, not the provider. Only TRANSIENT/AUTH/NETWORK failures trigger fallback.

### 4.3 `previewChain()` — pre-form check

Returns the chain that would be attempted, without actually attempting. Used by `InvoicePayModal` to decide form-vs-notice. Identical to `resolveChain()` but does NOT throw on empty — returns `[]` so the caller can render the "unavailable" notice.

---

## 5. Health Checker Behavior

Sliding-window failure count, configured via `config('kompo-finance.health.*')`:

- `window_seconds` — default 600 (10 min)
- `failures_to_degrade` — default 3 consecutive (or 5 within window if non-consecutive)
- `failures_to_down` — default 8 within window
- `recovery_successes` — default 2 to flip degraded → healthy

Algorithm on `record(provider, team, outcome)`:
1. Update or insert `fin_provider_health_snapshots` row for `(team, provider)`:
   - On SUCCESS: `consecutive_failures = 0`, `last_success_at = now()`, status = `healthy`.
   - On FAILED (TRANSIENT/AUTH/NETWORK): `consecutive_failures++`, `last_failure_at = now()`. If `consecutive_failures >= failures_to_down` → `status = 'down'`. Else if `>= failures_to_degrade` → `degraded`.
   - On FAILED (PERMANENT — e.g. card declined): do NOT increment failure counter. Permanent customer errors don't reflect on provider health.

Status reads check the snapshot. If `status = 'down'` and `last_failure_at` is older than `window_seconds`, optimistically promote to `degraded` (half-open circuit) — let the next request through to test the waters.

---

## 6. Pre-Form Availability Gate

In `InvoicePayModal::getPaymentMethodFields()`:

```php
$context = new PaymentContext(
    $this->payable, $this->selectedPaymentMethod, $this->teamId
);

$chain = app(PaymentGatewayResolverInterface::class)->previewChain($context);

if (empty($chain)) {
    Log::warning('Payment form blocked: no available provider', [
        'team_id' => $this->teamId,
        'payable_id' => $this->payable->id,
        'payable_type' => get_class($this->payable),
        'method' => $this->selectedPaymentMethod->value,
    ]);

    return new PaymentUnavailableNotice(
        method: $this->selectedPaymentMethod,
        reason: 'no_healthy_provider',
    );
}

// At least one healthy provider — render its form
$primary = $chain[0];

if ($primary->getCheckoutFlow() === PaymentFlowEnum::INLINE) {
    return $primary->getPaymentForm($context);
}

// HOSTED_REDIRECT / HOSTED_POST: render a "Continue to Provider" button
return _Button(__('finance-continue-to-provider', ['provider' => $primary->getDisplayName()]))
    ->selfPost('initiateHostedCheckout')
    ->withLoadingIn('pay-button');
```

Also: the payment method selector itself (line 70-88) filters methods to those with at least one healthy provider. So a user with a single dead provider sees "No payment methods currently available" and a help link, not a confusing partial form.

`PaymentUnavailableNotice` is a small Kompo component:

```php
class PaymentUnavailableNotice extends Form
{
    public function render()
    {
        return _Rows(
            _Html(__('finance.payment-temporarily-unavailable'))
                ->class('text-lg font-semibold'),
            _Html(__('finance.payment-system-will-be-back-soon'))
                ->class('text-sm text-gray-600 mt-2'),
            _Button(__('finance.try-again'))
                ->onClick->refresh()
                ->class('mt-4'),
        );
    }
}
```

---

## 7. Moneris Adapter

### 7.1 Flow type

Moneris uses MPG (Moneris Hosted Tokenization / Hosted Pay Page). The flow:
1. Server-side: call Moneris `preload` endpoint with order details → receive `ticket`.
2. Client: redirect (POST or load script) to Moneris with `ticket`.
3. User enters card info on Moneris-hosted page (PCI scope outside our app).
4. Moneris redirects back to our `return_url` with response code.
5. Server-side: call Moneris `receipt` endpoint with `ticket` to confirm.

Maps to `PaymentFlowEnum::HOSTED_POST`.

### 7.2 `MonerisPaymentProvider` skeleton

```php
class MonerisPaymentProvider implements PaymentGatewayInterface
{
    use BasicGatewayTrait, RegistersWebhooks;

    public function getCode(): string { return 'moneris'; }
    public function getDisplayName(): string { return 'Moneris'; }
    public function getCheckoutFlow(): PaymentFlowEnum { return PaymentFlowEnum::HOSTED_POST; }

    public function getSupportedPaymentMethods(): array
    {
        return [
            PaymentMethodEnum::CREDIT_CARD,
            // Moneris also supports Interac Online — add when needed
        ];
    }

    public function getPaymentForm(PaymentContext $ctx): ?Element
    {
        return null;  // HOSTED_POST: no inline form
    }

    public function getHostedCheckoutUrl(PaymentContext $ctx): ?HostedCheckoutTicket
    {
        $client = $this->client();   // Uses $this->creds resolved via withCredentials()

        $ticket = $client->preload([
            'store_id' => $this->creds->get('store_id'),
            'api_token' => $this->creds->get('api_token'),
            'checkout_id' => $this->creds->get('checkout_id'),
            'order_no' => $ctx->payable->getPaymentReference(),  // our unique order id
            'amount' => $ctx->payable->getAmountDue(),
            'currency' => 'CAD',
            'return_url' => route('finance.payments.moneris.return'),
        ]);

        return new HostedCheckoutTicket(
            url: $this->mpgHost() . '/HPP/index.php',  // or chkt-init endpoint
            method: 'POST',
            fields: ['ticket' => $ticket['ticket']],
            ticketId: $ticket['ticket'],
            metadata: ['order_no' => $ctx->payable->getPaymentReference()],
        );
    }

    public function charge(PaymentContext $ctx, array $data): PaymentResult
    {
        // For HOSTED_POST, charge() handles the *return* — confirms via receipt API
        $receipt = $this->client()->receipt([
            'ticket' => $data['ticket'],
        ]);

        return $receipt['response_code'] < 50
            ? PaymentResult::success($receipt['txn_number'], $receipt['amount'])
            : PaymentResult::failed($receipt['message'] ?? 'Declined');
    }

    public function classifyError(\Throwable $e): ErrorClassification
    {
        return match (true) {
            $e instanceof MonerisAuthException => ErrorClassification::authFailure($e->getMessage()),
            $e instanceof MonerisNetworkException => ErrorClassification::transient($e->getMessage()),
            default => ErrorClassification::permanent($e->getMessage()),
        };
    }
}
```

### 7.3 Files to create

- `src/Billing/Providers/Moneris/MonerisPaymentProvider.php`
- `src/Billing/Providers/Moneris/MonerisWebhookProcessor.php` — for IPN-style notifications (Moneris also calls back asynchronously)
- `src/Billing/Providers/Moneris/MonerisClient.php` — HTTP wrapper over Moneris MPG API
- `src/Billing/Providers/Moneris/MonerisResponseCodeMap.php` — `00`-`49` = approved; `50`+ = declined; specific codes → ErrorClassification
- `src/Billing/Providers/Moneris/Form/MonerisReturnHandler.php` — the form that processes the redirect-back
- Route: `POST /finance/payments/moneris/return` → handles redirect-back, calls `charge()` with ticket.

### 7.4 Logic lifted (not code) from `MonerisPayment/`

- **`App_Code/PaiementGP.cs`** — request field names, signature format, host endpoints. Becomes `MonerisClient::preload()` / `MonerisClient::receipt()`.
- **`App_Code/TransactionPaiement.cs`** — Tokenized payment flow. Inform `getHostedCheckoutUrl()` POST payload shape.
- **`moneris-checkout-webapp-with-server.js`** — Confirms HOSTED_POST is the right flow (server preloads ticket, client POSTs).
- **`App_Code/Constants.cs`** — Host names (`esqa.moneris.com` test, `www3.moneris.com` prod), endpoint paths, default checkout IDs.
- **`App_Code/TransactionGetter.cs`** — Receipt lookup. Becomes `MonerisClient::receipt()`. Critical: Moneris IPNs are unreliable; we MUST poll receipt on return.
- **`APPLICATION_DOCUMENTATION.md`** — Flow narrative (read once for context).

### 7.5 Moneris-specific config

```php
// config/kompo-finance.php
'services' => [
    'moneris' => [
        'host' => env('MONERIS_HOST', 'esqa.moneris.com'),       // test default
        'store_id' => env('MONERIS_STORE_ID'),
        'api_token' => env('MONERIS_API_TOKEN'),
        'checkout_id' => env('MONERIS_CHECKOUT_ID'),             // MPG profile id
        'is_test' => env('MONERIS_TEST', true),
    ],
],
```

Per-team credentials override via `fin_provider_credentials` (per §2.2).

### 7.6 Moneris quirks (call out in PR)

- `order_no` must be **globally unique**. Use `payment_intent_id` (UUID).
- Response codes: `null` = system error (retry), `00`-`49` = approved, `50`+ = declined.
- Decimal handling: cents-as-int in receipt, dollars-as-decimal in preload. Bug magnet. Wrap in `SafeDecimal` casts on the way in/out.

---

## 8. SISC Settings UI

File: `SISC/app/Kompo/Teams/Settings/FinanceSettingsForm.php` (existing).

Add a new section "Payment Providers" containing one card per supported `PaymentMethodEnum` value. Each card:

```
┌──────────────────────────────────────────────────────────────┐
│  Credit Card                                                  │
│                                                                │
│  Mode: ( ) Single provider   (•) Fallback chain               │
│                                                                │
│  Active providers (drag to reorder by priority):              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ ☰  1.  Moneris    [healthy]    [credentials]   [X]    │  │
│  │ ☰  2.  Stripe     [degraded]   [credentials]   [X]    │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                                │
│  + Add provider:  [ Select ▾  ]   [ Add ]                     │
└──────────────────────────────────────────────────────────────┘
```

Implementation notes:
- Use `_Query` to render the active list; drag-reorder updates `priority` via `selfPost('reorder')->optimistic()`.
- "Mode" radio updates `mode` on all rows for this method; `selfPost('setMode', [method, mode])->kompoUpdateLabel`.
- "credentials" button opens a modal with the credential fields specific to that provider (each provider exposes a `getCredentialsForm()` method).
- "[healthy]/[degraded]/[down]" badge reads from `ProviderHealthChecker::status()` — refresh every 30s via `->every(30000)`.

### 8.1 Credentials form per provider

Add to `PaymentGatewayInterface`:

```php
public function getCredentialsForm(?ProviderCredentials $current = null): Element;
public function validateCredentials(array $input): array;  // returns sanitized, throws on invalid
```

Stripe form: `secret_key`, `publishable_key`, `webhook_secret`.
BNA form: `api_key`, `merchant_id`, `webhook_secret`.
Moneris form: `store_id`, `api_token`, `checkout_id`, `is_test` toggle.

On save, the form encrypts and stores in `fin_provider_credentials`. Never exposes the secret values back to the UI (one-way; if the user wants to change, they enter new values).

### 8.2 Optional "Test connection" button

Each credential modal has a "Test connection" button that runs a provider-specific health probe (`StripeClient::accounts->retrieve()`, Moneris `verify-credentials` call, etc.). Reports OK/fail without persisting. Avoids "save → first transaction fails because typo in API key" pain.

---

## 9. Single-Mode vs Fallback-Mode Semantics

Per row, `mode` controls failure behavior:

| Mode | Behavior on primary failure | Behavior on PERMANENT error (card declined) |
|------|-----------------------------|---------------------------------------------|
| `single` | Surface the error to the user immediately. No retry. | Surface to user (same — these aren't provider issues). |
| `fallback` | Try the next provider in chain. If all fail, surface the *last* error. | Surface to user (do not try next provider for declined cards). |

The pre-form gate runs the same way regardless of mode: if no provider is healthy, show the notice. The difference only matters once a charge is attempted.

---

## 10. Migration Strategy

### 10.1 Schema

New migrations (in `database/migrations/`):
- `2026_05_13_000001_create_fin_provider_credentials_table.php`
- `2026_05_13_000002_create_fin_team_payment_providers_table.php`
- `2026_05_13_000003_extend_fin_payment_traces_with_team_and_classification.php`
- `2026_05_13_000004_create_fin_provider_health_snapshots_table.php` (optional)

### 10.2 Data backfill

For every existing team, generate one row per `(team, method, current-mapped-provider)` from the current `config('kompo-finance.payment_method_providers')` map, with `priority = 1`, `mode = 'single'`, `is_active = true`, `credentials_id = null` (use env defaults).

That preserves current behavior exactly. Teams opt in to multi-provider / fallback by editing the settings UI.

### 10.3 Code-path migration

1. **Phase 1 (this PR):** Migrations + new tables + resolver reads from new table with fallback to old config if no row. Existing code paths unchanged.
2. **Phase 2:** Add health checker + `resolveChain()`; pre-form gate; SISC UI.
3. **Phase 3:** Add Moneris.
4. **Phase 4 (later):** Remove the static `config('kompo-finance.payment_method_providers')` fallback once all teams have rows. Mark deprecated in CHANGELOG one release before removal.

### 10.4 Backwards-compat shim

`PaymentMethodEnum::getDefaultPaymentGateway()` stays but is marked `@deprecated`. It still works for any caller that hasn't migrated; internally it delegates to the resolver with a null team context (uses the static config fallback path).

---

## 11. Observability

### 11.1 Structured logs

A `PaymentLog` helper writes consistently shaped events:

```php
PaymentLog::charge(
    teamId: 42, providerCode: 'stripe', payableId: 1234,
    payableType: Invoice::class, action: 'charge',
    outcome: 'success', latencyMs: 380, reasonCode: null,
);
```

Fields land in a single log channel (`payment-events`) so they can be shipped to whatever backend (Loki, Elastic) without touching app code later.

### 11.2 Metrics (later)

Counter: `fin_payment_attempts_total{provider, team, outcome}`.
Histogram: `fin_payment_latency_ms{provider}`.
Gauge: `fin_provider_health{provider, team}` (0=down, 1=degraded, 2=healthy).

Not part of this PR; documented here so the log/trace shape is right from day one.

### 11.3 Admin diagnostics page (optional)

A small Kompo page under `/admin/finance/providers` showing:
- Current health per provider per team
- Last 50 failed attempts with reason codes
- "Force health refresh" button (calls `ProviderHealthChecker::recheck()`)

Not part of this PR.

---

## 12. Testing Strategy

- **Unit:** `PaymentProviderRegistry`, `DefaultPaymentGatewayResolver::resolveChain()` with mocked health checker (all healthy, primary down, all down, mode=single vs fallback). `ErrorClassification` matrix per provider.
- **Integration:** `PaymentProcessor::processPayment()` with mocked providers: success, primary fails + secondary succeeds, all fail, permanent error on primary (no fallback), single-mode hard fail.
- **Health checker:** sliding window thresholds — feed N synthetic outcomes and assert state transitions.
- **Webhook:** existing tests + a "BNA verifySignature throws" assertion (security regression).
- **Moneris:** full hosted-redirect round-trip mocked (preload returns ticket → charge() with ticket → receipt API mocked OK/declined). Test polling-vs-IPN reconciliation.

---

## 13. Risks & Open Questions

1. **Moneris IPN reliability.** Moneris IPNs are documented as best-effort. Our return handler must call the `receipt` API on every return; if a user closes the browser before redirect, we need a sweep cron to reconcile pending tickets. Suggested: 15-minute cron polling open tickets older than 5 minutes.
2. **Per-team Stripe webhook secrets.** Stripe webhooks are sent to a single endpoint with a single signing secret per Stripe account. If each team has its own Stripe account, each team needs its own webhook endpoint OR we use a different mechanism to disambiguate (Connect, custom accounts, route prefix). **Open question for the user — does each team have its own Stripe account, or is it one Stripe account with metadata to attribute team?**
3. **Health snapshot table vs on-the-fly compute.** If team count × provider count stays small (< 10k rows), on-the-fly with Laravel cache is simpler. Recommendation: skip the snapshot table for now; add later if pre-form latency becomes an issue.
4. **Credential storage threat model.** `encrypted` cast uses `APP_KEY`. If `APP_KEY` is leaked, every credential is exposed. Mitigations: rotate `APP_KEY` policy, consider a Vault/KMS integration later. Document the model.
5. **PaymentMethodEnum stability.** Adding new methods (e.g., Apple Pay) shouldn't break the schema. `payment_method_id` is just an integer in `fin_team_payment_providers`; new enum cases just work. Good.
6. **What about non-team payables?** Some payables in the system may not belong to a team (e.g., platform fees). The resolver should accept `team_id = null` and fall back to a "global" config row in `fin_team_payment_providers` with `team_id IS NULL`. Add this in the migration.

---

## 14. Implementation Order (matches task list)

1. ✅ BNA `verifySignature()` throws — done.
2. ✅ `watch()` bridge bug — done.
3. **Core contracts + enums + value objects** (`PaymentFlowEnum`, `ErrorClassification`, `HostedCheckoutTicket`, `NextAction`, `NoProviderAvailableException`, extended `PaymentGatewayInterface`, `BasicGatewayTrait`, new `ProviderHealthCheckerInterface`, extended resolver interface, `team_id` on `PaymentContext`).
4. **Migrations** for the four tables + payment_traces extension.
5. **Models** for `TeamPaymentProvider`, `ProviderCredentials`, `ProviderHealthSnapshot`.
6. **`DefaultProviderHealthChecker`** + service-provider binding.
7. **`DefaultPaymentGatewayResolver` rewrite** — `resolveChain()`, `previewChain()`, typed exception.
8. **`PaymentProcessor` rewrite** — fallback loop, structured logging trait.
9. **Adapt existing providers** (Stripe, BNA): add `BasicGatewayTrait`, implement `classifyError`, `getCredentialsForm`, `withCredentials`. Move secrets to use `withCredentials` flow with env fallback.
10. **Pre-form gate** — `InvoicePayModal` + `PaymentUnavailableNotice` component.
11. **SISC `FinanceSettingsForm`** — provider list UI per method.
12. **Moneris provider** — full implementation + return handler route + reconciliation cron.
13. **JS-bridge collapse work** (parallel track — independent from above): invoice computed totals, refresh chains → kompoMulti, jQuery polling & MutationObserver.

Steps 3–12 are all in the `kompo/finance` package except #11 which is in SISC. Step 13 spans many files but is independent.

---

*End of design.*
