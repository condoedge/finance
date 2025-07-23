<?php

use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Facades\TaxService;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\SegmentValue;

\Kompo\Elements\Element::macro('asCurrency', function () {
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
        )->class('px-4 py-2 text-right font-semibold'.($border ? '' : ' -mt-4'));
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

if (!function_exists('_AccountsSelect')) {
    function _AccountsSelect($label = '', ?GlAccount $account = null)
    {
        return _Select($label)->placeholder('finance-account')
            ->default($account?->getLastSegmentValue()->id ?? null)
            ->options(SegmentValue::forLastSegment()->get()->mapWithKeys(
                fn ($it) => [$it->id => $it->segment_value . ' - ' . $it->segment_description]
            ));
    }
}

if (!function_exists('_TaxesSelect')) {
    function _TaxesSelect($invoiceDetail = null, $name = 'taxes_ids', $context = [])
    {
        $invoice = $invoiceDetail?->invoice;

        $taxesOptions = TaxModel::active()->forTeam(currentTeamId())->get()->pluck('complete_label_html', 'id')->union($invoiceDetail?->invoiceTaxes()->with('tax')->get()->mapWithKeys(
            fn ($it) => [$it->tax->id => $it->complete_label_html]
        ));

        return	_MultiSelect()->placeholder('taxes')
            ->name($name)
            ->default($invoiceDetail?->id ? $invoiceDetail->invoiceTaxes()->pluck('tax_id') : TaxService::getDefaultTaxesIds([
                'invoice' => $invoice,
                ...$context,
            ]))
            ->options($taxesOptions);
    }
}
