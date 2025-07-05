<?php

if (!function_exists('integerArray')) {
    function integerArray(array $array): array {
        return array_map('intval', $array);
    }
}