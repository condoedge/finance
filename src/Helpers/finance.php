<?php


function _TaxesInfoLink()
{
	return _Link()->icon('question-mark-circle')->class('text-gray-700 absolute')
		->selfGet('getTaxesInfoModal')->inModal();
}