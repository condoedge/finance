<?php

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