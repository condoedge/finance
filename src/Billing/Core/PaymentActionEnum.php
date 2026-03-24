<?php

namespace Condoedge\Finance\Billing\Core;

enum PaymentActionEnum: string
{
    use \Kompo\Models\Traits\EnumKompo;

    case REDIRECT = 'redirect';
    case MODAL = 'modal';

    public function execute(PaymentResult $result)
    {
        return match ($this) {
            self::REDIRECT => redirect()->away($result->redirectUrl),
            self::MODAL => (new $result->options['modal']())(null, $result->options),
        };
    }

    public function executeIntoKompoPanel(PaymentResult $result)
    {
        return match ($this) {
            self::REDIRECT => response()->kompoRedirect($result->redirectUrl),
            self::MODAL => response()->modal((new $result->options['modal'])($result->options)),
        };
    }
}
