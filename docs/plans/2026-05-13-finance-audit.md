# Finance Module Audit — 2026-05-13

**Scope:** `kompo/finance` package. Deep on payments/providers; light elsewhere. Read-only audit; no code changes.

**Goal:** Prioritized punch list to inform follow-up work:
1. Dynamic, team-configurable payment provider system (ordering, fallback, health check, pre-form failure UX).
2. Moneris provider (joins Bna + Stripe).
3. Collapse legacy JS into the new Kompo `$k` bridge / surgical response API.
4. Surface weak spots and pending TODOs across the module.

**Severity legend:** P0 = critical (security, data loss, blocks goal). P1 = should-fix (correctness, friction, blocks goal indirectly). P2 = nice-to-have (cleanup, polish).

**How to read:** Each section is independently actionable. Sections 1–2 are the dynamic-provider seam map (intent: design doc next, not in this audit). Section 3 lists JS-bridge collapse targets. Section 4 lists modal/chained-response opportunities. Section 5 is the light sweep.

---

## Table of Contents

1. [Provider Layer — Findings](#1-provider-layer--findings)
2. [Dynamic-Provider Seam Map](#2-dynamic-provider-seam-map)
3. [JS-Bridge Collapse Candidates](#3-js-bridge-collapse-candidates)
4. [Modal / Chained-Response Opportunities](#4-modal--chained-response-opportunities)
5. [Light Sweep — Non-Payment Modules](#5-light-sweep--non-payment-modules)
6. [Recommended Next Steps](#6-recommended-next-steps)

---

## 1. Provider Layer — Findings

### 1.1 Critical (P0)

| # | Where | Issue | Recommendation |
|---|-------|-------|----------------|
| 1.1.1 | `src/Billing/Providers/Bna/BnaWebhookProcessor.php:27-31` | **BNA `verifySignature()` returns `true` unconditionally** ("they don't provide a signature verification method"). Webhooks fully unauthenticated — any attacker can forge a BNA webhook to mark a payment APPROVED. | Until BNA exposes signing, restrict the route to BNA IP allowlist + add HMAC over a shared secret (BNA-provided merchant id + timestamp) and reject on mismatch. Log every unverified hit. Make signature-verification mandatory in the contract. |
| 1.1.2 | `src/Models/PaymentMethodEnum.php:68-71` + `config/kompo-finance.php:23-27` | **Hard-coded payment-method→provider class map in config.** No team context, no DB lookup, no fallback. This is the single biggest blocker for the dynamic-provider goal. | Move mapping out of the enum entirely. Introduce a `team_payment_providers` table (team_id, payment_method_id, provider_code, priority, is_active). Resolver queries it. |
| 1.1.3 | `src/Billing/Core/Resolver/DefaultPaymentGatewayResolver.php:17-28` | **No fallback chain.** `resolve()` picks one provider; if it errors there is no retry/next. Coupled with 1.1.2, kills the fallback goal. | Introduce `resolveChain(PaymentContext): iterable<Gateway>` returning the team's ordered active providers. Caller iterates until one succeeds or all fail. |
| 1.1.4 | `src/Billing/Core/PaymentProcessor.php:75-90` | **Single-provider transaction path.** Catches exception, rolls back, returns failure — no fallback attempt. | Wrap the resolve-and-charge step in a loop over `resolveChain()`. Persist each attempt to `payment_traces` so the failure history is queryable. |
| 1.1.5 | `src/Billing/Core/` (whole subtree) | **No health-check / circuit-breaker mechanism.** Form renders even if provider is down. | Introduce `ProviderHealthChecker` service (looks at last N `payment_traces` per provider, exposes `isHealthy(code): bool` and a circuit-breaker open/half-open/closed state). Resolver consults it; pre-form code consults it. |
| 1.1.6 | `src/Kompo/InvoicePayModal.php:110-122` (`getPaymentMethodFields`) | **No pre-form provider availability check.** User fills form, submits, then gets an error. | Before calling `PaymentProcessor::getPaymentForm()`, ask `ProviderHealthChecker` if the resolved chain has *any* healthy provider. If none, return an error component ("Payments temporarily unavailable — try again in a few minutes") and emit a structured log. |
| 1.1.7 | `src/Billing/Contracts/PaymentGatewayInterface.php` | **No flow-type discriminator** (inline form / hosted-redirect / hosted-iframe). Moneris hosted checkout is a redirect-out → return flow that the current contract doesn't model. | Add `getCheckoutFlow(): PaymentFlowEnum {INLINE, HOSTED_REDIRECT, HOSTED_IFRAME}` and `getHostedCheckoutUrl(PaymentContext): ?string`. Default to INLINE in a trait so existing providers don't need changes. |

### 1.2 Should-Fix (P1)

| # | Where | Issue | Recommendation |
|---|-------|-------|----------------|
| 1.2.1 | `Resolver/DefaultPaymentGatewayResolver.php:22-23` | `abort(403)` when method has no provider → user sees a 403 page, not a friendly "system down" view. | Throw a typed `NoProviderAvailableException`. Caller renders the friendly view; webhook callers re-queue. |
| 1.2.2 | `Core/PaymentProviderRegistry.php:29-36` | `get()` throws generic `\Exception`. | Throw `PaymentProcessingException::providerNotRegistered($code)`. |
| 1.2.3 | `Core/PaymentProcessor.php:57-65` | Failed-payment log lacks provider code and team id. | Add a structured logging trait used by every provider call. Always log `{team_id, payable_id, payable_type, provider_code, action, latency_ms, outcome, reason_code}`. |
| 1.2.4 | `Core/WebhookProcessor.php:29-35` | Duplicate-processing lock is cache-only (30s). Cleared cache or multi-server can replay. | Combine cache lock with DB unique index on `(provider_code, event_id)`. Treat unique-violation as "already processed, ack 200". |
| 1.2.5 | `Core/WebhookProcessor.php:47-54, 207-216` | If a provider is removed from config, orphan webhook events sit forever; `loadPayable()` returns nullable model silently. | Add a `webhook_events.status` enum (received/processing/processed/dead-letter/orphaned). Cron sweeps orphaned. |
| 1.2.6 | `Providers/Stripe/StripeWebhookProcessor.php:28-69` | Custom HMAC verification — works today but only supports Stripe `v1` signature. | Use `Stripe\Webhook::constructEvent()` from the SDK directly. Future signature versions handled by Stripe. |
| 1.2.7 | `Core/WebhookProcessor.php:91-128` | `PaymentMethodEnum::from()` will throw if an enum case is removed and an old webhook arrives. | Use `tryFrom()` and dead-letter on null. |
| 1.2.8 | `Providers/Stripe/StripeWebhookProcessor.php:156-176` | Charge-succeeded and payment-intent-succeeded events can arrive out-of-order. The "already processed" guard catches the common case but not all races. | Make payment marking idempotent at the payment-row level (UPSERT on `payment_intent_id` with `processed_at` timestamp); reject if already non-null. |
| 1.2.9 | `Core/PaymentContext.php:12` | No `team_id` field — resolver cannot look up team-specific provider mapping. | Add `team_id` (nullable; resolved from `payable` via `team()` if absent). Required for the dynamic-provider table. |
| 1.2.10 | `config/kompo-finance.php:29-40` | Provider secrets env-only — blocks multi-tenant (different Stripe account per team). | Add per-team secret storage (encrypted in DB) and a `getCredentials(team_id)` hook on providers. Env stays as fallback default. |
| 1.2.11 | `Providers/Stripe/StripePaymentProvider.php:35-48` and `Bna/BnaPaymentProvider.php:39-44` | Constructors read secrets once and stash them — singleton-incompatible with per-team credentials. | Lazy credential resolution at call-time using `PaymentContext->team_id`. Cache per `(team_id, provider_code)` for the request. |
| 1.2.12 | `Providers/Stripe/StripePaymentProvider.php:56-62` | `getSupportedPaymentMethods()` is a hardcoded array. With per-team selection ("team prefers Moneris for CC, Stripe for ACH"), the resolver can't compose it. | Keep the hardcoded list as the *capability* (what the provider *can* do); add a separate `getEnabledMethods(team_id)` lookup against the new mapping table. |
| 1.2.13 | `Resolver/DefaultPaymentGatewayResolver.php:31-42` | `getAvailableGateways()` returns global registry filtered by capability — no team filter, no health filter. | Filter by `team_payment_providers` (active rows for team, ordered by priority) intersected with `ProviderHealthChecker::healthy()`. |
| 1.2.14 | `Contracts/PaymentCanReturnModal.php` | Only Bna implements it. Name implies a single flow; no hosted-redirect counterpart. | Replace with a richer flow-type enum (see 1.1.7) and retire the marker interface. |
| 1.2.15 | `Core/PaymentResult.php:32-44` | `pending()` accepts `redirectUrl` but no flag for "hosted redirect / POST form / GET redirect / show modal". | Add a `next_action` shape: `{type: 'redirect'|'modal'|'post_form', url, payload}`. Moneris needs the `post_form` variant. |
| 1.2.16 | `Core/PaymentActionEnum.php:12-27` | Only REDIRECT (GET) and MODAL. No POST-redirect. | Add `HOSTED_POST` action; client renders a self-submitting form. |
| 1.2.17 | `Kompo/InvoicePayModal.php:118-121` | Expects `getPaymentForm()` to return a Kompo element. Moneris will return `null` + redirect URL. | Branch on `flow_type`: render form if INLINE, render "Continue to Moneris" button if HOSTED_REDIRECT (button does the POST-redirect). |
| 1.2.18 | `Core/PaymentProcessor.php:81-88` | Original exception context discarded when wrapped. | Wrap with `previous` parameter so stack traces are preserved, and copy provider/team/action onto the wrapping exception's context array. |
| 1.2.19 | `Billing/Exceptions/PaymentProcessingException.php:10-23` | Re-throws `ValidationException` on line 19, losing the outer context. | Don't re-throw — wrap. ValidationException is a kind of payment-processing failure for reporting purposes. |
| 1.2.20 | `Core/PaymentProcessor.php:62-65, 107-109` | `$payable->onPaymentFailed()` is fire-and-forget — no hook for "trigger fallback". Webhook processor `loadPayable()` throws `RuntimeException` if payable model deleted. | Failed callback returns a `FailureAction` enum (`STOP`, `RETRY_FALLBACK`, `DEFER`). Webhook processor dead-letters on missing payable. |
| 1.2.21 | `Billing/Models/PaymentTrace` schema | Missing columns: `team_id`, `failure_reason_code`, `retry_count`, `latency_ms`. Cannot answer "Which provider is failing for which team this week?" | Migration to add these. Populate retrospectively from logs if available. |
| 1.2.22 | `Core/WebhookProcessor.php:67-82, 225` | `isNonRecoverableError()` is coarse. Stripe rate-limit (transient) and card-declined (permanent) treated the same. | Each provider implements `classifyError(\Throwable): ErrorClassification {TRANSIENT, PERMANENT, AUTH, RATE_LIMIT, NETWORK}`. Webhook processor uses it to decide retry vs ack. |
| 1.2.23 | `Contracts/PaymentGatewayResolverInterface.php` | No method to query health or fetch fallback. | Add `resolveChain(PaymentContext): iterable`, `healthOf(string $code): HealthStatus`. Existing `resolve()` can stay as "first in chain". |
| 1.2.24 | `Kompo/InvoicePayModal.php:70-88` | Method selector hard-codes `PaymentMethod::isOnlinePayment()` — no provider-availability filter on the method list itself. | Method list should be: methods that have *at least one healthy provider* for this team. Otherwise the user picks a method that can't be served. |

### 1.3 Nice-to-Have (P2)

| # | Where | Issue | Recommendation |
|---|-------|-------|----------------|
| 1.3.1 | `Providers/Stripe/StripePaymentProvider.php:35-48` | Empty Stripe key logs critical but still returns a broken instance. | Throw `MisconfiguredProviderException` at construction; surfaces during the resolver health check instead of mid-charge. |
| 1.3.2 | `Providers/Bna/BnaPaymentProvider.php:89-100` | Logs failures without team / reason-code context. | Apply the structured logging trait (1.2.3). |
| 1.3.3 | `CondoedgeFinanceServiceProvider.php:344` (registry binding) | Singleton — incompatible with per-team registries. | If we ever want per-team registry instances, bind as `bind` not `singleton` and inject team-id-aware factory. |
| 1.3.4 | All providers | No timeouts set on Guzzle / Stripe client. | Set sane request timeouts (10–15s connect, 30s total). A hung provider stalls the whole request and pollutes health stats. |

---

## 2. Dynamic-Provider Seam Map

The places to modify when implementing the dynamic-provider design. **This is a seam map, not a design** — the design lives in a follow-up doc.

### 2.1 Data model additions

- New table `team_payment_providers` — columns: `team_id`, `payment_method_id`, `provider_code`, `priority` (smallint), `is_active` (bool), `credentials_id` (nullable FK to encrypted secrets), `updated_at`.
- New table `provider_credentials` — encrypted per-team secrets (`team_id`, `provider_code`, `credentials` JSON encrypted at rest).
- Extend `payment_traces` per 1.2.21.
- Optional `provider_health_snapshots` — for fast read in pre-form check (`provider_code`, `team_id`, `status`, `last_failure_at`, `consecutive_failures`).

### 2.2 Contract changes

- `PaymentGatewayInterface`: add `getCheckoutFlow()`, `getHostedCheckoutUrl(PaymentContext)`, `getCapabilities()`, `classifyError(\Throwable)`.
- `PaymentGatewayResolverInterface`: add `resolveChain(PaymentContext)`, `healthOf(string)`.
- `PaymentResult`: add `next_action` shape (see 1.2.15).
- Retire `PaymentCanReturnModal` (see 1.2.14).
- New `ProviderHealthCheckerInterface` with `isHealthy(code, team_id)`, `record(code, team_id, outcome)`, `state(code, team_id)`.

### 2.3 Resolver changes — current → target

- **Current path:** `PaymentMethodEnum::getDefaultPaymentGateway()` → class string → `app()->make()` → `registry->get(code)` → done.
- **Target path:** `team_payment_providers.where(team_id, payment_method_id).orderBy(priority).where(is_active)` → filter by `ProviderHealthChecker::isHealthy()` → return ordered chain.
- **Fallback path:** caller iterates chain, calls `record()` on each outcome, returns first success or `NoProviderAvailableException` on exhaustion.

### 2.4 UI insertion points

- **SISC `app/Kompo/Teams/Settings/FinanceSettingsForm.php`** — add a "Payment providers" section: per payment method, a sortable list of enabled providers with priority; toggle for single-only vs fallback mode; credential entry per provider.
- **Finance `Kompo/InvoicePayModal.php`** — call health check before rendering form (see 1.1.6); render friendly error component on hard failure.
- **New `Kompo/Common/PaymentUnavailableNotice.php`** — reusable error view for the "system down" state.

### 2.5 Moneris adapter — what to lift from `MonerisPayment/`

The reference is an ASP.NET app (don't lift the C# directly). What's relevant:

- `MonerisPayment/App_Code/PaiementGP.cs` — call signatures, fields submitted, response codes. Lift the field-shape and response-code map into a PHP `MonerisRequest`/`MonerisResponse` value object.
- `MonerisPayment/App_Code/TransactionPaiement.cs` — hosted-checkout (Moneris Checkout / "MPG") request preparation. Maps to our future `MonerisPaymentProvider::createCheckoutSession()`.
- `MonerisPayment/moneris-checkout-webapp-with-server.js` — confirms the hosted-redirect flow (POST → ticket → load script → user submits on Moneris → return with response). Confirms we need `HOSTED_POST` action (1.2.16).
- `MonerisPayment/App_Code/Constants.cs` — credential names, endpoint URLs, test vs prod store IDs.
- `MonerisPayment/App_Code/TransactionGetter.cs` — post-payment lookup (we'll need it for webhook reconciliation since Moneris webhooks are sparse).
- `MonerisPayment/APPLICATION_DOCUMENTATION.md` — flow narrative.

Moneris quirks to bake into the design doc:
- Hosted checkout returns user via configured `Return URL`; the merchant must call `Receipt Request` to confirm — webhook alone is insufficient.
- Test environment uses `esqa.moneris.com`; prod is `www3.moneris.com`. Both need env-configurable hosts.
- Response codes: `00`-`49` = approved, `50`+ = declined, `null` = system error.
- Idempotency via `order_id` — must be globally unique. Our `payment_intent_id` candidate.

---

## 3. JS-Bridge Collapse Candidates

The new bridge gives us `field()`, `panel()`, `el()`, `watch()`, `$k.query(id).add/update/remove`, `jsShowWhen`, `jsComputed`, `kompoMulti`, `addToQuery`/`updateInQuery`/`removeFromQuery`, `kompoUpdateLabel`/`kompoUpdateElementValues`, `optimistic()`, `withLoadingIn()`, `every()`/`after()`/`throttle()`. The collapse targets are mostly in payment forms and table CRUD flows.

### 3.1 P0 — Manual DOM / jQuery polling that should be bridge calls

#### `src/Kompo/CustomerForm.php:56, 149-188` — jQuery-polling auto-fill

**Now:**
```php
_Hidden()->onLoad->run($this->jsToSelectCustomer());
// jsToSelectCustomer(): 37 lines of jQuery, setInterval, dispatchEvent
```

**Should be:**
```php
_Hidden()->onLoad->run('({ form, el }) => {
    const info = el("customer-after-save-info");
    form.fill({ customer_id: info.data("id") });
}')
```

#### `src/Kompo/InvoiceForm.php:53-70` — `MutationObserver` for error styling

**Now:** A `MutationObserver` watches `.total_amount_error`, on non-empty content adds `!text-danger` to `#invoice_total_amount` and its `.vlHtml` child, and scrolls into view.

**Should be:** Declarative:
```php
_ErrorField()->name('total_amount_error', false)
    ->jsShowWhen('total_amount_error', '!=', null);

_Html(...)->id('invoice_total_amount')
    ->jsAddClass('!text-danger')->jsShowWhen('total_amount_error', '!=', null)
    ->jsScrollTo();
```

#### `src/Kompo/SegmentManagement/SegmentsValuesPage.php:50-54` — `setInterval` poll + `location.reload()`

**Now:**
```php
->run('() => {
    const interval = setInterval(() => {
        if ($(".select-on-create .vlOptions span[data-id]").length) {
            clearInterval(interval);
            // ...
        }
    }, 30);
    utils.setLoadingScreen();
    location.reload();
}')
```

**Should be:** Replace `location.reload()` with `response()->kompoRefresh('segments-values-page')`. The polling loop can become `->every(50)` with an early exit using `data.set('done', true)` once the option appears.

### 3.2 P1 — `.refresh()` chains that should be surgical `kompoMulti`

| Where | Now | Should be |
|-------|-----|-----------|
| `SelectMissingInfoInvoice.php:27-29` | `->closeModal()->refresh('invoice-page')->alert(...)` | `response()->kompoMulti([closeModal(), kompoUpdateLabel('invoice-page-status','Approved'), kompoAlert(...)])` |
| `ExpenseReports/ExpenseForm.php:43-46` | `->closeModal()->refresh(['expenses-query','expense-report-total'])` | `kompoMulti([closeModal(), addToQuery('expenses-query', $row), kompoUpdateLabel('expense-report-total', $total)])` |
| `ExpenseReports/ExpenseReportForm.php:56-59` | `->closeModal()->refresh(['user-expense-report-table'])` | `kompoMulti([closeModal(), addToQuery('user-expense-report-table', $row)])` |
| `Taxes/TaxForm.php:56-58` | `->closeModal()->alert(...)->refresh('taxes-table')` | `kompoMulti([closeModal(), addToQuery('taxes-table', $row), kompoAlert(...)])` |
| `PaymentTerms/PaymentTermForm.php:41-42` | `->closeModal()->browse('payment-terms-table')->alert(...)` | `kompoMulti([closeModal(), addToQuery('payment-terms-table', $row), kompoAlert(...)])` |
| `PaymentForm.php:106-107` | `->refresh($this->refreshId)->closeModal()->when(...)` | `kompoMulti([...])` with conditional spread |
| `SegmentManagement/SegmentValueFormModal.php:84` | `->closeModal()->refresh(['segments-values-page','finance-chart-of-accounts'])` | `kompoMulti([closeModal(), updateInQuery('segments-values-page', "segment-{$id}", $row), kompoRefresh('finance-chart-of-accounts')])` |
| `FiscalSetup/FiscalSetupForm.php:28` | `->alert(...)->refresh('finance-fiscal-setup-page')` | Single `kompoUpdateLabel` if only one field changed; full refresh is overkill |

### 3.3 P1 — Conditional show/hide that should be `jsShowWhen`

| Where | Should be |
|-------|-----------|
| `InvoicePayModal.php:75-77` (`$this->model->payment_term_id ? null : _Select(...)`) | `_Select(...)->jsShowWhen('payment_term_id','==',null)` |
| `InvoicePayModal.php:86-89` (payment method conditional) | `->jsShowWhen('payment_method_id','==',null)` |
| `InvoicePage.php:70-79` (approve button with `->when(hasMissingInfo, ...)`) | The branch on `hasMissingInfo` is server-side state — keep server branch but use `jsShowWhen` on the *resulting* button for status changes. |
| `InvoiceDetailForm.php:97-104` (conditional `_DeleteLink` vs `_Link(deleted)`) | Render both, gate with `jsShowWhen('id','==',null)` / `'!='`. Avoids re-render. |

### 3.4 P1 — Computed totals — invoice form

The most valuable single collapse. Currently `InvoiceDetailForm.php:45, 66, 71, 79, 90` triggers `calculateTotals` (external JS) via `_Hidden()->onLoad->run('calculateTotals')` and on every numeric input change.

**Should be:**
```php
_FinanceCurrency()->id('detail-extended-price')
    ->jsComputed(['quantity', 'unit_price'], 'quantity * unit_price');

// tax line:
_FinanceCurrency()->id('detail-tax')
    ->jsComputed(['detail-extended-price', 'tax_rate'], 'extended_price * tax_rate');

// invoice totals in InvoiceForm.php:185-203:
_TotalFinanceCurrencyCols(...)->id('invoice-subtotal')
    ->jsComputed(['invoiceDetails[*].extended_price'], 'sum(invoiceDetails[*].extended_price)');
```

(The `invoiceDetails[*]` syntax is a proposed extension — verify whether the current `jsComputed` engine supports it; if not, do a single `watchAll` in the form's `onLoad`.)

### 3.5 P1 — Optimistic table actions

| Where | Now | Should be |
|-------|-----|-----------|
| `SegmentManagement/SegmentsValuesPage.php:85-88` | toggle active link → `selfPost(...)->refresh()` | `selfPost(...)->optimistic()->jsToggleClass('row-{id}','opacity-50')->then(updateInQuery(...))` |
| `SegmentManagement/SegmentsTable.php:42-47` | edit/delete → `selfPost()->refresh()` | `optimistic()` + `jsRemoveFromQuery` + server confirms |
| `PaymentTerms/PaymentTermsTable.php:48` | `_DeleteLink` with full table refresh | Add `->optimistic()->jsRemoveFromQuery('payment-terms-table',"term-{$id}")` |

### 3.6 P2 — Polling and lazy loading

- `InvoiceForm.php:161` — due-date lookup via `selfGet('getSubmitInfoPanel')->inPanel(...)` could be `jsComputed(['invoice_date','payment_term_id'], 'calculateDueDate(...)')` if the rule is expressible client-side. If not (DB-driven payment terms), keep server-side but consider client-side caching with `data.set`.
- `SegmentsValuesPage.php:50-54` — `setInterval` polling for option visibility → `->every(50)` with early exit.

### 3.7 P2 — Loading-state management

- `InvoicePayModal.php:102` — manual `utils.removeLoadingScreen()` in `onError`. Use `->withLoadingIn('pay-button')` so the framework manages it.
- `GlTransactions/GlTransactionForm.php` — submit button has no loading state. Add `->withLoadingIn('gl-transaction-form')`.

### 3.8 P2 — Already-good patterns (no change, kept for reference)

- `InvoicePayModal.php:54-60` — `kompoMulti` for post-pay sequence. ✅
- `InvoiceForm.php:142` — `selfGet('getCustomerModal')->inModal()` is the clean bridge pattern. ✅
- `Taxes/TaxesTable.php:32` — `selfGet('getTaxForm', ...)->inModal()`. ✅
- `Product/ProductRebateForm.php:45-49` — `kompoMulti` with conditional update/add. ✅

---

## 4. Modal / Chained-Response Opportunities

These overlap with §3 but call out the modal/drawer flows specifically, since you flagged them.

| # | Where | Issue | Fix |
|---|-------|-------|-----|
| 4.1 | `PaymentForm.php:65-70` | After saving a payment, instantiates `new ApplyPaymentToInvoiceModal(...)` and returns the object — a two-step "save then open" via PHP return value. | Use `response()->kompoMulti([closeModal(), kompoOpenModal(ApplyPaymentToInvoiceModal::class, [...])])` so it's explicit and chainable. |
| 4.2 | `ApplyPaymentToInvoiceModal.php:77-79` | `onChange` does two sequential `selfGet().inPanel()` calls relying on `&&` evaluation order — fragile. | Single `selfGet('refreshBoth')` returning `kompoMulti([panel(...), panel(...)])`. |
| 4.3 | `InvoicePage.php:122` | `->selfUpdate('getApplyPaymentToInvoiceModal')->inModal()` — `selfUpdate` is a PUT/PATCH; this is just a GET for a modal. Misuse. | Rename to `selfGet()`. |
| 4.4 | `PaymentEntryForm.php:138-142` | Three `_HugeButton`s each call a different `getRegularPaymentForm` / `getAdvancePaymentForm` / `getInvoiceCreditNotesForm` endpoint — divergence. | One endpoint `loadPaymentSubform($type)` selecting subform via switch; cleaner test surface. |
| 4.5 | `Common/Modal.php` (and similar) | Verify all modal containers expose stable IDs so `kompoOpenModal` and `updateInQuery` references don't break on refactor. | Document modal-ID convention in `docs/`. |

---

## 5. Light Sweep — Non-Payment Modules

Flat punch list; one line each. P0/P1/P2 noted inline.

### 5.1 TODO / FIXME

- P1 `src/Services/Customer/CustomerService.php:109` — TODO placeholder for customer due-amount calc; returns `SafeDecimal('0.00')` and logs error. Caller cannot distinguish "no balance" from "calc failed".
- P1 `src/Services/Invoice/InvoiceService.php:273` — TODO for payment-method→account mapping; unimplemented.
- P1 `src/Services/InvoiceDetail/InvoiceDetailService.php:155` — TODO tax-amount placeholder.
- P1 `src/Services/InvoiceDetail/InvoiceDetailService.php:172` — TODO total-amount placeholder.
- P1 `src/Services/Payment/PaymentService.php:133` — TODO payment-amount-left placeholder.
- P1 `src/Models/Product.php:195,222` — TODO: real default-revenue-account lookup; currently falls back to `SegmentValue::first()` which can return arbitrary or null.
- P2 `src/Models/Traits/HasRelationsManager.php:11` — TODO refactor to use Graph.
- P2 `src/Kompo/InvoiceForm.php:51` — TODO refactor for new Kompo version (this is the `MutationObserver` block in §3.1).
- P2 `src/Kompo/GlTransactions/GlTransactionsTable.php:149` — `getReverseModal()` empty stub.

### 5.2 Controllers / Validation

- P1 `Http/Controllers/Api/GlTransactionController.php:90` — `store()` missing return statement on success path (only returns in catch). Likely returns `null` → 200 with empty body.
- P1 `Http/Controllers/Api/GlTransactionController.php:105` — `findOrFail('id', $transactionId)` — wrong signature; should be `findOrFail($transactionId)`.
- P1 `Http/Controllers/Api/AccountSegmentController.php:73-83` — generic `\Exception` catch + `DB::rollBack()` but no `DB::beginTransaction()` in this method (transaction is in service). Rollback is a no-op or, worse, rolls back an outer transaction.
- P1 `Http/Controllers/Api/GlTransactionController.php:92-97` — same `DB::rollBack()` without local `beginTransaction`. Confusing and possibly wrong.
- P2 `Http/Controllers/Api/AccountSegmentController.php:39,69` and `GlTransactionController.php:70,103` — no type hints on id parameters.
- P2 `Http/Controllers/Api/GlTransactionController.php:150-156` — validation rules inline; should be a FormRequest.
- P2 `Http/Controllers/Api/CustomersController.php:15-35` and `TaxesController.php:15-30` — inconsistent response shapes; some return localized strings, others raw English messages.
- P2 `Http/Controllers/Api/GlTransactionController.php:50` — magic number `50` for default `per_page`; promote to constant.

### 5.3 Service-layer integrity

- P2 `Services/AccountSegmentService.php:171` — `'TEMP'` hardcoded as placeholder for descriptor. Promote to enum / constant.
- P2 `Services/AccountSegmentService.php:50-65` — `executeInTransaction` re-throws after logging; document caller contract.
- P2 `Services/Customer/CustomerService.php:105-110`, `InvoiceDetail/InvoiceDetailService.php:151-157`, `Payment/PaymentService.php:129-135` — silent failure pattern: log error + return `SafeDecimal('0.00')`. Callers can't distinguish recoverable from unrecoverable. Either throw, or return `Result/Option`.

### 5.4 Models

- P1 `Models/Product.php:195,222` — `SegmentValue::first()` fallback can return null; subsequent `->id` access NPEs.
- P1 `Models/Product.php:162-173` — `getAmountWithRebates()` calls `rebates()->count()` lazily; risks N+1 when iterated.
- P2 `Models/Product.php:181-250`, `Models/InvoiceDetail.php:73-100`, `Models/InvoiceDetailTax.php:68-104`, `Models/Customer.php:143-148` — multiple deprecated static-factory methods preserved for back-compat. Add a removal deadline in PHPDoc and a CHANGELOG entry.

### 5.5 Tests & docs

- P1 No tests visible for ExpenseReports, ChartOfAccounts, FiscalSetup modules.
- P2 No package README.md at repo root.
- P2 No CHANGELOG with deprecation timeline.
- P2 Commands (`Command/PreCreateFiscalPeriodsCommand`, `EnsureInvoiceEventsAreProcessed`, etc.) — verify `$signature` and `$description` are set and informative.

### 5.6 Already-good patterns worth preserving

- `Casts/SafeDecimal.php` — strict decimal handling. Don't replace with floats anywhere.
- `Services/AccountSegmentService.php::executeInTransaction()` — consistent transaction wrapper. Use it instead of ad-hoc `DB::beginTransaction()`.
- Event-driven invoice lifecycle (`Events/InvoiceGenerated`, `InvoiceDetailGenerated`) — extensible without modifying core.

---

## 6. Recommended Next Steps

In priority order:

1. **P0 security: BNA webhook signature** (§1.1.1) — fastest, isolated, high-impact. IP allowlist + shared HMAC until BNA exposes a real signature. Should not wait for the dynamic-provider redesign.
2. **Dynamic-provider design doc** — turn §2 (seam map) into a design doc (`docs/plans/2026-05-XX-dynamic-provider-design.md`). Cover: data model migration, contract changes, resolver rewrite, health-checker, SISC settings UI mockup, Moneris adapter shape.
3. **Moneris adapter** — implement against the new contracts from step 2. Lift logic (not code) from `MonerisPayment/App_Code/PaiementGP.cs` and `TransactionPaiement.cs`. Add `HOSTED_POST` action to `PaymentActionEnum`.
4. **JS-bridge collapse — invoice form computed totals** (§3.4) — single highest-impact UX win; replaces the bulk of the `MutationObserver`/`calculateTotals` code paths.
5. **JS-bridge collapse — `.refresh()` chains → `kompoMulti`** (§3.2) — table-driven; can be done module-by-module without coordination.
6. **JS-bridge collapse — jQuery polling and `MutationObserver`** (§3.1) — touches customer + invoice forms; do after computed-totals so we're not editing the same file twice.
7. **P1 light-sweep TODOs** — knock out the service-layer placeholders (§5.1) one at a time. They each return `'0.00'` today, so production is silently wrong wherever those flows fire.

---

*End of audit.*
