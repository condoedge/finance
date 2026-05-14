<?php

namespace Condoedge\Finance\Kompo\Settings;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface;
use Condoedge\Finance\Billing\Contracts\ProviderHealthCheckerInterface;
use Condoedge\Finance\Billing\Core\PaymentProviderRegistry;
use Condoedge\Finance\Billing\Core\ProviderHealthState;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\TeamPaymentProvider;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * Per-team payment-provider configuration UI. Embed in SISC's FinanceSettingsForm
 * via:  new \Condoedge\Finance\Kompo\Settings\PaymentProvidersSettings(null, ['team_id' => $team->id])
 *
 * For each payment method that has at least one capable provider, renders:
 *   - mode toggle: single (no fallback) vs fallback (try chain on failure)
 *   - ordered list of enabled providers (priority asc) with up/down arrows
 *   - health badge per row (healthy / degraded / down)
 *   - remove + edit-credentials buttons
 *   - add-another-provider selector
 *
 * All mutations are surgical (audit §3.5): toggle priority via updateInQuery,
 * delete via removeFromQuery + optimistic, add via prependToQuery — no full
 * page refreshes. Health badges refresh every 30s via ->every().
 */
class PaymentProvidersSettings extends Form
{
    public $class = 'space-y-6';

    /**
     * Kompo Auth picks this up to gate access to the form — both render and
     * the mutation methods (setMode, reorder, addProvider, removeRow,
     * getCredentialsModal). Provider config controls how money is collected,
     * so only roles granted PaymentProviders should reach it.
     */
    public $permissionKey = 'PaymentProviders';

    protected int $teamId;

    public function created()
    {
        $this->teamId = (int) ($this->prop('team_id') ?? currentTeamId());
    }

    public function render()
    {
        $methods = $this->methodsWithCapableProviders();

        if ($methods->isEmpty()) {
            return _Html('finance.no-payment-providers-installed')->class('text-gray-500');
        }

        return _Rows(
            _Html('finance.payment-providers-settings')->class('text-xl font-semibold mb-2'),
            _Html('finance.payment-providers-settings-help')->class('text-sm text-gray-600 mb-4'),
            $methods->map(fn (PaymentMethodEnum $m) => $this->methodCard($m))->toArray(),
        );
    }

    protected function methodCard(PaymentMethodEnum $method)
    {
        $rows = TeamPaymentProvider::chainFor($this->teamId, $method);
        $mode = $rows->first()->mode ?? TeamPaymentProvider::MODE_SINGLE;

        $availableCodes = $this->capableProviderCodes($method);
        $usedCodes = $rows->pluck('provider_code')->all();
        $addable = array_diff($availableCodes, $usedCodes);

        return _CardLevel4(
            _Flex(
                _Html($method->label())->class('text-lg font-semibold flex-grow'),
                _Radio('finance.mode-single')
                    ->name("mode_{$method->value}")
                    ->value(TeamPaymentProvider::MODE_SINGLE)
                    ->default($mode)
                    ->onChange->selfPost('setMode', [
                        'method' => $method->value,
                        'mode' => TeamPaymentProvider::MODE_SINGLE,
                    ])->jsAlert('finance.settings-saved', 'success'),
                _Radio('finance.mode-fallback')
                    ->name("mode_{$method->value}")
                    ->value(TeamPaymentProvider::MODE_FALLBACK)
                    ->default($mode)
                    ->onChange->selfPost('setMode', [
                        'method' => $method->value,
                        'mode' => TeamPaymentProvider::MODE_FALLBACK,
                    ])->jsAlert('finance.settings-saved', 'success'),
            )->class('items-center mb-3 gap-4'),
            _Panel(
                $this->providerList($method, $rows),
            )->id("providers-list-{$method->value}"),
            !empty($addable) ? $this->addProviderRow($method, $addable) : null,
        )->class('p-4 mb-4');
    }

    protected function providerList(PaymentMethodEnum $method, $rows)
    {
        if ($rows->isEmpty()) {
            return _Html('finance.no-providers-enabled')->class('text-sm text-gray-500 italic');
        }

        return _Rows(
            $rows->map(fn ($row, $i) => $this->providerRow($method, $row, $i, $rows->count()))->toArray(),
        );
    }

    protected function providerRow(PaymentMethodEnum $method, TeamPaymentProvider $row, int $index, int $total)
    {
        $registry = app(PaymentProviderRegistry::class);
        $provider = $registry->has($row->provider_code) ? $registry->get($row->provider_code) : null;
        $displayName = $provider?->getDisplayName() ?? $row->provider_code;

        $health = app(ProviderHealthCheckerInterface::class)
            ->status($row->provider_code, $this->teamId);
        $badgeClass = match ($health->state) {
            ProviderHealthState::HEALTHY => 'bg-green-100 text-green-800',
            ProviderHealthState::DEGRADED => 'bg-yellow-100 text-yellow-800',
            ProviderHealthState::DOWN => 'bg-red-100 text-red-800',
        };

        return _Flex(
            _Html("#{$row->priority}")->class('w-12 text-gray-500 font-mono'),
            _Html($displayName)->class('font-medium flex-grow'),
            _Html(__('finance.health-' . $health->state->value))
                ->class("text-xs px-2 py-1 rounded {$badgeClass}")
                ->every(30000)
                ->selfGet('refreshHealthBadge', ['row_id' => $row->id])
                ->inPanel("health-badge-{$row->id}"),
            $index > 0
                ? _Link()->icon('arrow-up')->selfPost('reorder', ['row_id' => $row->id, 'direction' => 'up'])
                    ->inPanel("providers-list-{$method->value}")
                : _Html('')->class('w-6'),
            $index < $total - 1
                ? _Link()->icon('arrow-down')->selfPost('reorder', ['row_id' => $row->id, 'direction' => 'down'])
                    ->inPanel("providers-list-{$method->value}")
                : _Html('')->class('w-6'),
            // Per-team credentials are meaningless when the install forces a
            // single global account — hide the button entirely in that mode.
            config('kompo-finance.force_global_credentials', false)
                ? null
                : _Link()->icon('settings')->selfGet('getCredentialsModal', ['row_id' => $row->id])->inModal(),
            _Link()->icon('trash')->class('text-danger')
                ->selfPost('removeRow', ['row_id' => $row->id])
                ->optimistic()
                ->jsRemoveFromQuery("providers-list-{$method->value}", "row-{$row->id}")
                ->inPanel("providers-list-{$method->value}"),
        )->id("row-{$row->id}")->class('items-center gap-2 py-2 border-b last:border-b-0');
    }

    protected function addProviderRow(PaymentMethodEnum $method, array $addable): mixed
    {
        $registry = app(PaymentProviderRegistry::class);
        $options = [];
        foreach ($addable as $code) {
            $options[$code] = $registry->has($code)
                ? $registry->get($code)->getDisplayName()
                : $code;
        }

        return _Flex(
            _Select()->name("add_provider_{$method->value}", false)
                ->options($options)
                ->placeholder('finance.select-provider')
                ->class('flex-grow'),
            _Button('finance.add-provider')
                ->selfPost('addProvider', ['method' => $method->value])
                ->inPanel("providers-list-{$method->value}"),
        )->class('mt-3 gap-2 items-end');
    }

    // ===========================
    // Mutations
    // ===========================

    public function setMode()
    {
        $method = PaymentMethodEnum::from((int) request('method'));
        $mode = request('mode');

        TeamPaymentProvider::where('team_id', $this->teamId)
            ->where('payment_method_id', $method)
            ->update(['mode' => $mode]);

        return response()->json(['ok' => true]);
    }

    public function reorder()
    {
        $row = TeamPaymentProvider::findOrFail((int) request('row_id'));
        $this->assertOwnsRow($row);

        $direction = request('direction');
        $adjacent = TeamPaymentProvider::where('team_id', $this->teamId)
            ->where('payment_method_id', $row->payment_method_id)
            ->when($direction === 'up',
                fn ($q) => $q->where('priority', '<', $row->priority)->orderByDesc('priority'),
                fn ($q) => $q->where('priority', '>', $row->priority)->orderBy('priority'),
            )
            ->first();

        if ($adjacent) {
            [$row->priority, $adjacent->priority] = [$adjacent->priority, $row->priority];
            $row->save();
            $adjacent->save();
        }

        $rows = TeamPaymentProvider::chainFor($this->teamId, $row->payment_method_id instanceof PaymentMethodEnum
            ? $row->payment_method_id
            : PaymentMethodEnum::from((int) $row->payment_method_id));

        return $this->providerList(
            PaymentMethodEnum::from((int) $row->payment_method_id->value ?? (int) $row->payment_method_id),
            $rows,
        );
    }

    public function addProvider()
    {
        $method = PaymentMethodEnum::from((int) request('method'));
        $code = request("add_provider_{$method->value}");

        if (!$code) {
            abort(422, __('finance.no-provider-selected'));
        }

        $maxPriority = TeamPaymentProvider::where('team_id', $this->teamId)
            ->where('payment_method_id', $method)
            ->max('priority') ?? 0;

        TeamPaymentProvider::create([
            'team_id' => $this->teamId,
            'payment_method_id' => $method->value,
            'provider_code' => $code,
            'priority' => $maxPriority + 1,
            'is_active' => true,
            'mode' => TeamPaymentProvider::MODE_SINGLE,
        ]);

        return $this->providerList($method, TeamPaymentProvider::chainFor($this->teamId, $method));
    }

    public function removeRow()
    {
        $row = TeamPaymentProvider::findOrFail((int) request('row_id'));
        $this->assertOwnsRow($row);
        $method = $row->payment_method_id;
        $row->delete();

        return $this->providerList(
            $method instanceof PaymentMethodEnum ? $method : PaymentMethodEnum::from((int) $method),
            TeamPaymentProvider::chainFor(
                $this->teamId,
                $method instanceof PaymentMethodEnum ? $method : PaymentMethodEnum::from((int) $method),
            ),
        );
    }

    public function getCredentialsModal()
    {
        // Defense in depth: the button is hidden in force_global_credentials
        // mode, but block the endpoint too in case it's reached directly.
        if (config('kompo-finance.force_global_credentials', false)) {
            abort(403, __('finance.credentials-managed-globally'));
        }

        $row = TeamPaymentProvider::findOrFail((int) request('row_id'));
        $this->assertOwnsRow($row);

        return new ProviderCredentialsFormModal(null, [
            'row_id' => $row->id,
        ]);
    }

    public function refreshHealthBadge()
    {
        $row = TeamPaymentProvider::findOrFail((int) request('row_id'));
        $health = app(ProviderHealthCheckerInterface::class)
            ->status($row->provider_code, $this->teamId);

        return _Html(__('finance.health-' . $health->state->value));
    }

    // ===========================
    // Helpers
    // ===========================

    /**
     * @return \Illuminate\Support\Collection<PaymentMethodEnum>
     */
    protected function methodsWithCapableProviders()
    {
        $registry = app(PaymentProviderRegistry::class);
        $methods = collect();

        foreach (PaymentMethodEnum::cases() as $method) {
            if (!$method->online()) {
                continue;
            }
            foreach ($registry->all() as $provider) {
                if (in_array($method, $provider->getSupportedPaymentMethods(), true)) {
                    $methods->push($method);
                    break;
                }
            }
        }

        return $methods;
    }

    /**
     * @return array<string>
     */
    protected function capableProviderCodes(PaymentMethodEnum $method): array
    {
        $codes = [];
        foreach (app(PaymentProviderRegistry::class)->all() as $provider) {
            if (in_array($method, $provider->getSupportedPaymentMethods(), true)) {
                $codes[] = $provider->getCode();
            }
        }
        return $codes;
    }

    protected function assertOwnsRow(TeamPaymentProvider $row): void
    {
        if ($row->team_id !== $this->teamId) {
            abort(403);
        }
    }
}
