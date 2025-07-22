<?php

use Condoedge\Finance\Components\LinkWithConfirmation;

if (!function_exists('_LinkWithConfirmation')) {
    function _LinkWithConfirmation() 
    {
        return LinkWithConfirmation::form(...func_get_args());
    }
}