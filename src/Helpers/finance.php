<?php

function _TotalCurrencyCols($title, $id, $amount = 0, $border = true)
{
    return _Columns(
        _Html($title)->class('text-level1 font-medium'),
        _Currency($amount)->id($id)->class('ccy-amount text-lg text-level1')
    )->class('px-4 py-2 text-right font-semibold'.($border ? '': ' -mt-4'));
}

function _TaxesInfoLink()
{
	return _Link()->icon('question-mark-circle')->class('text-gray-700 absolute')
		->selfGet('getTaxesInfoModal')->inModal();
}