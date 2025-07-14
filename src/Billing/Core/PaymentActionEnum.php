<?php

namespace Condoedge\Finance\Billing\Core;

enum PaymentActionEnum: string
{
    use \Kompo\Models\Traits\EnumKompo;

    case REDIRECT = 'redirect';

    public function execute(PaymentResult $result)
    {
        return match ($this) {
            self::REDIRECT => redirect()->away($result->redirectUrl),
        };
    }

    public function executeIntoKompoPanel(PaymentResult $result)
    {
        return match ($this) {
            self::REDIRECT => _Rows(
                _Hidden()->onLoad(fn ($e) => $e->run('() => {
                    utils.setLoadingScreen();

                    window.location.href = "' . $result->redirectUrl . '";
                }'))
            ),
        };
    }
}
