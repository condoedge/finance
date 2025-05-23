<?php

\Kompo\Elements\Element::macro('asCurrency', function(){
    return $this->label(finance_currency($this->label));
});

if (!function_exists('_FinanceModalHeader')) {
    function _FinanceModalHeader($els)
    {
        return _FlexBetween(
            $els,
        )
        ->class('px-8 pt-6 pb-4 rounded-t-2xl')
        ->class('flex-col items-start md:flex-row md:items-center')
        ->alignStart();
    }
}

if (!function_exists('_FinanceCurrency')) {
    function _FinanceCurrency($value, $options = null)
    {
        return _Html(finance_html_currency($value, $options));
    }
}

if (!function_exists('_TotalFinanceCurrencyCols')) {
    function _TotalFinanceCurrencyCols($title, $id, $amount = 0, $border = true)
    {
        return _Columns(
            _Html($title)->class('text-level1 font-medium title-currency'),
            _FinanceCurrency($amount ?? 0)->id($id)->class('ccy-amount text-lg text-level1')
        )->class('px-4 py-2 text-right font-semibold'.($border ? '': ' -mt-4'));
    }
}

if (!function_exists('_MiniLabelFinanceCcy')) {
    function _MiniLabelFinanceCcy($label, $date, $class = '')
    {
        return _Rows(
            _MiniLabel($label),
            _FinanceCurrency($date)->class($class),
        );
    }
}

