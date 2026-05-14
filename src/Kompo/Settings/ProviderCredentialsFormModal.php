<?php

namespace Condoedge\Finance\Kompo\Settings;

use Condoedge\Finance\Billing\Core\PaymentProviderRegistry;
use Condoedge\Finance\Models\ProviderCredentials;
use Condoedge\Finance\Models\TeamPaymentProvider;
use Condoedge\Utils\Kompo\Common\Modal;

/**
 * Per-provider credential entry modal. Renders a dynamic form based on which
 * provider this row is for (each provider declares its credential fields in
 * getCredentialFields(), or we fall back to a generic key/value sheet).
 *
 * Secrets are write-only on the UI side — the existing credential values are
 * never sent back to the browser. Users see masked placeholders ("•••••") and
 * blanking a field means "leave unchanged".
 */
class ProviderCredentialsFormModal extends Modal
{
    protected ?TeamPaymentProvider $row = null;
    protected ?ProviderCredentials $existing = null;

    public function created()
    {
        $this->row = TeamPaymentProvider::findOrFail((int) $this->prop('row_id'));
        $this->existing = ProviderCredentials::lookup(
            $this->row->team_id,
            $this->row->provider_code,
            isTest: false,
        );
    }

    public function headerTitle()
    {
        $registry = app(PaymentProviderRegistry::class);
        $display = $registry->has($this->row->provider_code)
            ? $registry->get($this->row->provider_code)->getDisplayName()
            : $this->row->provider_code;

        return __('finance.configure-provider', ['provider' => $display]);
    }

    public function body()
    {
        return _Rows(
            _Html(__('finance.credentials-help'))->class('text-sm text-gray-600 mb-4'),
            $this->credentialFields(),
            _Toggle('finance.is-test-mode')->name('is_test', false)
                ->default((bool) $this->existing?->is_test)
                ->class('mt-4'),
            _FlexEnd(
                _Button('generic.cancel')->onClick->closeModal()->class('btn-secondary'),
                _SubmitButton('generic.save')->class('btn-primary'),
            )->class('mt-6 gap-2'),
        );
    }

    protected function credentialFields()
    {
        // Provider-specific fields per design §8.1. Hardcoded mapping for now;
        // when we add more providers, move this into a getCredentialFields()
        // method on PaymentGatewayInterface.
        return match ($this->row->provider_code) {
            'stripe' => _Rows(
                _Input('finance.stripe-secret-key')->name('secret_key', false)
                    ->placeholder($this->maskedPlaceholder('secret_key', 'sk_live_•••••')),
                _Input('finance.stripe-publishable-key')->name('publishable_key', false)
                    ->placeholder($this->maskedPlaceholder('publishable_key', 'pk_live_•••••')),
                _Input('finance.stripe-webhook-secret')->name('webhook_secret', false)
                    ->placeholder($this->maskedPlaceholder('webhook_secret', 'whsec_•••••')),
            ),
            'bna' => _Rows(
                _Input('finance.bna-api-url')->name('api_url', false)
                    ->default($this->existing?->get('api_url')),
                _Input('finance.bna-api-key')->name('api_key', false)
                    ->placeholder($this->maskedPlaceholder('api_key')),
                _Input('finance.bna-api-secret')->name('api_secret', false)
                    ->placeholder($this->maskedPlaceholder('api_secret')),
            ),
            'moneris' => _Rows(
                _Input('finance.moneris-host')->name('host', false)
                    ->default($this->existing?->get('host', 'esqa.moneris.com')),
                _Input('finance.moneris-store-id')->name('store_id', false)
                    ->default($this->existing?->get('store_id')),
                _Input('finance.moneris-api-token')->name('api_token', false)
                    ->placeholder($this->maskedPlaceholder('api_token')),
                _Input('finance.moneris-checkout-id')->name('checkout_id', false)
                    ->default($this->existing?->get('checkout_id')),
            ),
            default => _Textarea('finance.credentials-json')
                ->name('credentials_json', false)
                ->placeholder('{"key": "value"}'),
        };
    }

    public function handle()
    {
        $merged = $this->existing?->credentials ?? [];

        // Only overwrite fields the user actually filled in (blank = unchanged).
        $fields = match ($this->row->provider_code) {
            'stripe' => ['secret_key', 'publishable_key', 'webhook_secret'],
            'bna' => ['api_url', 'api_key', 'api_secret'],
            'moneris' => ['host', 'store_id', 'api_token', 'checkout_id'],
            default => [],
        };

        foreach ($fields as $field) {
            $value = request($field);
            if ($value !== null && $value !== '') {
                $merged[$field] = $value;
            }
        }

        if (request('credentials_json')) {
            $decoded = json_decode(request('credentials_json'), true);
            if (is_array($decoded)) {
                $merged = array_merge($merged, $decoded);
            }
        }

        $creds = ProviderCredentials::updateOrCreate(
            [
                'team_id' => $this->row->team_id,
                'provider_code' => $this->row->provider_code,
                'is_test' => (bool) request('is_test'),
            ],
            [
                'credentials' => $merged,
                'last_rotated_at' => now(),
            ],
        );

        $this->row->credentials_id = $creds->id;
        $this->row->save();

        return response()->kompoMulti([
            response()->closeModal(),
            response()->kompoAlert(__('finance.credentials-saved'), 'success'),
        ]);
    }

    /**
     * Show "•••••" if a value already exists, blank otherwise. Never echo
     * the actual secret back to the client.
     */
    protected function maskedPlaceholder(string $key, string $fallback = '•••••••••'): string
    {
        return $this->existing?->has($key) ? $fallback : '';
    }
}
