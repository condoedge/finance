<?php

if (!function_exists('financeScriptFile')) {
    function financeScriptFile()
    {
        return file_get_contents(__DIR__.'/../../resources/js/finance.js');
        ;
    }
}
