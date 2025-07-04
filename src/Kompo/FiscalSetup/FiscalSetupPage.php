<?php

namespace Condoedge\Finance\Kompo\FiscalSetup;

use Condoedge\Utils\Kompo\Common\Form;

class FiscalSetupPage extends Form
{
    public $id = 'finance-fiscal-setup-page';

    public function render()
    {
        return _Rows(
            _Html('finance-fiscal-setup')->class('text-2xl font-bold mb-4'),
            new FiscalSetupForm(),
            _Html('finance-periods')->class('text-2xl font-bold mb-4'),
            new FiscalSetupPeriods(),
        );
    }
}
