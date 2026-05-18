<?php

namespace Condoedge\Finance\Kompo\Common;

use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * Friendly notice rendered when InvoicePayModal's pre-form gate decides no
 * provider is currently usable for the selected payment method. See audit
 * §1.1.6 and design §6 — replaces the previous "render form, fail on submit"
 * behavior that left the user staring at a generic 400 page.
 *
 * Constructed via:
 *   new PaymentUnavailableNotice(['method' => $method->value, 'reason' => $r])
 */
class PaymentUnavailableNotice extends Form
{
    public $class = 'text-center p-6';

    protected ?PaymentMethodEnum $method = null;
    protected string $reason = 'no_healthy_provider';

    public function created()
    {
        $methodValue = $this->prop('method');
        if ($methodValue !== null) {
            $this->method = PaymentMethodEnum::from((int) $methodValue);
        }
        $this->reason = $this->prop('reason') ?? 'no_healthy_provider';
    }

    public function render()
    {
        return _Rows(
            _Html('!')->icon('icon-warning')->class('text-warning text-4xl mb-4'),
            _Html(__('finance.payment-temporarily-unavailable'))
                ->class('text-xl font-semibold text-level1 mb-2'),
            _Html($this->reasonMessage())
                ->class('text-sm text-gray-600 mb-6'),
            _FlexCenter(
                _Button('finance.try-again')
                    ->icon('icon-refresh')
                    ->onClick->refresh()
                    ->class('btn-secondary mr-2'),
                _Button('finance.close')
                    ->onClick->closeModal()
                    ->class('btn-primary'),
            )->class('gap-2'),
        );
    }

    private function reasonMessage(): string
    {
        return match ($this->reason) {
            'all_down' => __('finance.payment-providers-all-down'),
            'none_configured' => __('finance.payment-providers-none-configured'),
            default => __('finance.payment-system-will-be-back-soon'),
        };
    }
}
