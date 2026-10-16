<?php

if (!function_exists('financeScriptFile')) {
    function financeScriptFile()
    {
        // The request's currency config, so asCurrency() formats a live recalculation like finance_currency() does.
        return 'var financeCurrency = ' . json_encode(get_currency_config()) . ";\n"
            . file_get_contents(__DIR__.'/../../resources/js/finance.js');
    }
}
