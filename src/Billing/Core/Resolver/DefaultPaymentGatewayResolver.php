<?php

namespace Condoedge\Finance\Billing\Core\Resolver;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;
use Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface;
use Condoedge\Finance\Billing\Contracts\ProviderHealthCheckerInterface;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentLog;
use Condoedge\Finance\Billing\Core\ProviderHealthState;
use Condoedge\Finance\Billing\Core\PaymentProviderRegistry;
use Condoedge\Finance\Billing\Exceptions\NoProviderAvailableException;
use Condoedge\Finance\Models\ProviderCredentials;
use Condoedge\Finance\Models\TeamPaymentProvider;

/**
 * Resolves the ordered fallback chain of providers for a payment context.
 *
 * Lookup order:
 *  1. fin_team_payment_providers rows for (team_id, payment_method_id), priority asc, is_active=true.
 *  2. If empty AND team_id was set, try team_id=NULL global defaults.
 *  3. If still empty, fall back to the legacy config('kompo-finance.payment_method_providers') map.
 *  4. Otherwise: NoProviderAvailableException::noneConfigured.
 *
 * Each chain entry is built by:
 *  - app()->make(provider_class) to instantiate the provider
 *  - ProviderCredentials::lookup(team_id, code) to find per-team or global creds
 *  - Passing creds into the provider via withCredentials() if it implements the contract
 *
 * Health filter:
 *  - HEALTHY providers go first.
 *  - DEGRADED providers go last (still attempted).
 *  - DOWN providers are skipped.
 *
 * Per-row mode:
 *  - mode=single returns only the primary, even if there are lower-priority rows.
 *  - mode=fallback returns the full chain.
 */
class DefaultPaymentGatewayResolver implements PaymentGatewayResolverInterface
{
    /**
     * Memoized per-team "primary provider codes" lookup. Keyed by team id (or
     * 'global'). Value is an array of codes, or null when the team has no rows
     * at all (pure legacy config — no primary/fallback distinction).
     *
     * @var array<int|string, array<string>|null>
     */
    private array $primaryCodesCache = [];

    public function __construct(
        private PaymentProviderRegistry $registry,
        private ProviderHealthCheckerInterface $healthChecker,
    ) {
    }

    public function resolve(PaymentContext $context): PaymentGatewayInterface
    {
        $chain = $this->resolveChain($context);
        $chainArray = is_array($chain) ? $chain : iterator_to_array($chain);

        if (empty($chainArray)) {
            throw NoProviderAvailableException::allDown(
                $context->getTeamId(),
                $context->paymentMethod,
                [],
            );
        }

        return $chainArray[0];
    }

    public function resolveChain(PaymentContext $context): iterable
    {
        $chain = $this->previewChain($context);

        if (empty($chain)) {
            // Distinguish "nothing configured" from "all dead" for better error messages.
            $rows = $this->lookupRows($context);
            if ($rows->isEmpty() && empty($this->legacyClasses($context))) {
                throw NoProviderAvailableException::noneConfigured(
                    $context->getTeamId(),
                    $context->paymentMethod,
                );
            }

            $codes = $rows->pluck('provider_code')->all();
            throw NoProviderAvailableException::allDown(
                $context->getTeamId(),
                $context->paymentMethod,
                $codes,
            );
        }

        return $chain;
    }

    public function previewChain(PaymentContext $context): array
    {
        $rows = $this->lookupRows($context);
        $teamId = $context->getTeamId();

        if ($rows->isEmpty()) {
            // Legacy config fallback (audit §1.2.13 / design §10.4).
            return $this->buildLegacyChain($context);
        }

        $primaryMode = $rows->first()->mode ?? TeamPaymentProvider::MODE_SINGLE;

        $healthy = [];
        $degraded = [];
        foreach ($rows as $row) {
            $provider = $this->buildProviderFromRow($row, $teamId);
            if (!$provider) {
                continue;
            }

            // Capability enforcement: a resolved chain only ever contains
            // providers that can actually serve this method. Guards against
            // misconfigured rows (e.g. an admin adding interac -> stripe).
            if (!$this->supportsMethod($provider, $context)) {
                continue;
            }

            $state = $this->healthChecker->status($row->provider_code, $teamId)->state;
            match ($state) {
                ProviderHealthState::HEALTHY => $healthy[] = $provider,
                ProviderHealthState::DEGRADED => $degraded[] = $provider,
                ProviderHealthState::DOWN => null, // skip
            };
        }

        $chain = array_merge($healthy, $degraded);

        if ($primaryMode === TeamPaymentProvider::MODE_SINGLE && !empty($chain)) {
            return [$chain[0]];
        }

        return $chain;
    }

    public function getAvailableGateways(PaymentContext $context): array
    {
        $available = [];

        foreach ($this->registry->all() as $provider) {
            if ($this->supportsMethod($provider, $context)) {
                $available[] = $provider;
            }
        }

        return $available;
    }

    public function isMethodAvailable(PaymentContext $context): bool
    {
        // previewChain is health-filtered and (above) capability-filtered, so an
        // empty chain means no healthy provider can serve this method at all.
        $chain = $this->previewChain($context);
        if (empty($chain)) {
            return false;
        }

        // Config ON: any healthy, capable provider in the chain is enough —
        // fallback-position providers contribute their methods too.
        if (config('kompo-finance.offer_fallback_provider_methods', false)) {
            return true;
        }

        // Config OFF: the method only shows if a "primary" provider serves it.
        // A provider is primary when it sits at priority 1 in any of the team's
        // rows. This is what hides e.g. Interac when BNA is only a credit-card
        // fallback (BNA never at priority 1 -> not primary -> Interac suppressed).
        $primaryCodes = $this->primaryProviderCodes($context->getTeamId());

        // null = team has no rows at all (pure legacy config). There is no
        // primary/fallback concept without rows, so don't gate — legacy behavior.
        if ($primaryCodes === null) {
            return true;
        }

        foreach ($chain as $provider) {
            if (in_array($provider->getCode(), $primaryCodes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a provider's declared capabilities include the context's method.
     */
    private function supportsMethod(PaymentGatewayInterface $provider, PaymentContext $context): bool
    {
        return in_array($context->paymentMethod, $provider->getSupportedPaymentMethods(), true);
    }

    /**
     * Distinct provider codes that sit at priority 1 in the team's active rows.
     * Mirrors TeamPaymentProvider::chainFor's team-vs-global resolution: team
     * rows win; global (team_id NULL) rows are the fallback when the team has
     * none of its own. Returns null when neither exists — the caller treats
     * that as "no primary/fallback concept, don't gate".
     *
     * @return array<string>|null
     */
    private function primaryProviderCodes(?int $teamId): ?array
    {
        $cacheKey = $teamId ?? 'global';
        if (array_key_exists($cacheKey, $this->primaryCodesCache)) {
            return $this->primaryCodesCache[$cacheKey];
        }

        $rows = collect();
        if ($teamId !== null) {
            $rows = TeamPaymentProvider::where('team_id', $teamId)
                ->where('is_active', true)
                ->get();
        }

        if ($rows->isEmpty()) {
            $rows = TeamPaymentProvider::whereNull('team_id')
                ->where('is_active', true)
                ->get();
        }

        if ($rows->isEmpty()) {
            return $this->primaryCodesCache[$cacheKey] = null;
        }

        $codes = $rows->where('priority', 1)
            ->pluck('provider_code')
            ->unique()
            ->values()
            ->all();

        return $this->primaryCodesCache[$cacheKey] = $codes;
    }

    /**
     * @return \Illuminate\Support\Collection<TeamPaymentProvider>
     */
    private function lookupRows(PaymentContext $context)
    {
        return TeamPaymentProvider::chainFor($context->getTeamId(), $context->paymentMethod);
    }

    private function buildProviderFromRow(TeamPaymentProvider $row, ?int $teamId): ?PaymentGatewayInterface
    {
        try {
            $provider = $this->registry->get($row->provider_code);
        } catch (\Throwable $e) {
            PaymentLog::unavailable(
                context: app(PaymentContext::class), // logging fallback if context missing
                reason: 'provider_not_registered',
                attemptedProviders: [$row->provider_code],
            );
            return null;
        }

        // Single-account installs (config force_global_credentials) skip the
        // fin_provider_credentials table entirely — the provider keeps its
        // env-backed config('kompo-finance.services.*') credentials.
        if (config('kompo-finance.force_global_credentials', false)) {
            return $provider;
        }

        $creds = $row->credentials_id
            ? $row->credentials
            : ProviderCredentials::lookup($teamId, $row->provider_code);

        return $this->applyCredentials($provider, $creds);
    }

    private function applyCredentials(PaymentGatewayInterface $provider, $creds): PaymentGatewayInterface
    {
        // Providers that support per-team credentials implement a withCredentials()
        // method. Stripe/BNA/Moneris each opt in independently; for those that
        // don't, we just return the provider as-is (uses env config).
        if ($creds && method_exists($provider, 'withCredentials')) {
            return $provider->withCredentials($creds);
        }

        return $provider;
    }

    /**
     * @return array<class-string>
     */
    private function legacyClasses(PaymentContext $context): array
    {
        $map = config('kompo-finance.payment_method_providers', []);
        $class = $map[$context->paymentMethod->value] ?? null;
        return $class ? [$class] : [];
    }

    /**
     * @return array<PaymentGatewayInterface>
     */
    private function buildLegacyChain(PaymentContext $context): array
    {
        $chain = [];
        foreach ($this->legacyClasses($context) as $class) {
            $provider = app($class);
            if (!$provider instanceof PaymentGatewayInterface) {
                continue;
            }

            // Capability enforcement applies to the legacy path too.
            if (!$this->supportsMethod($provider, $context)) {
                continue;
            }

            // No team-scoped health for legacy config — just use global health.
            $state = $this->healthChecker->status($provider->getCode(), null)->state;
            if ($state === ProviderHealthState::DOWN) {
                continue;
            }

            $chain[] = $provider;
        }
        return $chain;
    }
}
