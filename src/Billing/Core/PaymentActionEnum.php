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
            self::REDIRECT => _Rows(
                _Hidden()->onLoad(fn ($e) => $e->run('() => {
                    utils.setLoadingScreen();

                    window.location.href = "' . $result->redirectUrl . '";
                }'))
            ),
            self::MODAL => $this->openModalInElement($result->options),
        };
    }

    protected function openModalInElement($options)
    {
        $modalName = class_basename($options['modal']);

        return _Rows(
            _Button()->get('modal.' . $modalName, $options)
                ->inModal()->id('openModalInElement' . $modalName)->class('hidden'),
            _Hidden()->onLoad(fn ($e) => $e->run('() => {
                $("#openModalInElement' . $modalName . '").click();
            }'))
        );
    }
}
