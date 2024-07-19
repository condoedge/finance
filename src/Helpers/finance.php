<?php


function _DateLockErrorField()
{
    return _ErrorField()->name('not_editable', false);
}


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


function _MiniLabelDate($label, $date, $class = '')
{
    return _Rows(
        _MiniLabel($label),
        _HtmlDate($date)->class($class),
    );
}

function _MiniLabelCcy($label, $date, $class = '')
{
    return _Rows(
        _MiniLabel($label),
        _Currency($date)->class($class),
    );
}

function _MiniLabel($label)
{
    return _Html($label)->class('text-level1 text-opacity-50 text-xs');
}