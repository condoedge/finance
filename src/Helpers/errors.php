<?php

if(!function_exists('balanceLockedMessage')) {
    function balanceLockedMessage($date)
    {
        return __('finance-balance-locked', ['date' => $date]); 
    }
}
