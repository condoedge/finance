<?php

if (!function_exists('finance_html_currency')) {
    function finance_html_currency($value, $options = null)
    {
        return '<span class="currency"> ' . finance_currency($value, $options) . '</span>';
    }
}

if (!function_exists('finance_currency')) {
    function finance_currency($value, $options = null)
    {
        if (!$value) {
            return '-';
        }

        $value = (is_object($value) && method_exists($value, 'toFloat')) ? $value->toFloat() : (float) $value;

        $customFormatter  = config('kompo-finance.custom_currency_formatter', null);
        if ($customFormatter && is_callable($customFormatter)) {
            return call_user_func($customFormatter, $value);
        }

        extract(get_currency_config($options));

        // First filling in the minimum number of decimals
        $formatted = (string) $value;

        // Then, if the number of decimals exceeds the maximum, we round it
        if ($max_number_of_decimals < get_decimal_qty($formatted)) {
            $formatted = finance_round_value($value, $max_number_of_decimals, $rounding_mode);
        }

        $formatted = finance_ensure_decimals($formatted, $min_number_of_decimals);

        // Finally we ensure the separator is correct
        $formatted = number_format(
            $formatted,
            get_decimal_qty($formatted),
            $decimal_separator,
            $thousands_separator
        );

        return $position === 'left'
            ? $symbol . ' ' . $formatted
            : $formatted . ' ' . $symbol;
    }
}

if (!function_exists('db_decimal_format')) {
    function db_decimal_format($value, $precision = 5)
    {
        if (is_object($value) && method_exists($value, 'toFloat')) {
            $value = $value->toFloat();
        }

        return number_format($value, $precision, '.', '');
    }
}

if (!function_exists('db_datetime_format')) {
    function db_datetime_format($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }
}

if (!function_exists('db_date_format')) {
    function db_date_format($value)
    {
        return date('Y-m-d', strtotime($value));
    }
}

if (!function_exists('get_currency_config')) {
    function get_currency_config($overridingOptions = null)
    {
        $config = config('kompo-finance.currency', []);

        if (app()->has('config-currency')) {
            $config = array_merge($config, app('config-currency'));
        }

        if ($overridingOptions) {
            $config = array_merge($config, $overridingOptions);
        }

        if (isset($config['format'])) {
            $matches = [];

            $format = $config['format'];

            preg_match('/#|0/', $format, $matches, PREG_OFFSET_CAPTURE);
            $config['position'] = $matches[0][1] === 0 ? 'right' : 'left';
            $config['symbol'] = trim(substr($format, $config['position'] == 'left' ? 0 : -str_regex_pos(strrev($format), '/#|0/'), $config['position'] == 'left' ? str_regex_pos($format, '/#|0/') : strlen($format) - str_regex_pos(strrev($format), '/#|0/')));
            $config['thousands_separator'] = preg_match('/#+([^0-9])/', $format, $matches) ? $matches[1] : ',';
            $config['decimal_separator'] = preg_match('/\\' .$config['thousands_separator'] . '#+([^#])/', $format, $matches) ? $matches[1] : '.';
            $config['min_number_of_decimals'] = substr_count(substr($format, strpos($format, $config['decimal_separator'])), '0');
            $config['max_number_of_decimals'] = substr_count(substr($format, strpos($format, $config['decimal_separator'])), '#') + $config['min_number_of_decimals'];
        }

        return $config;
    }
}

if (!function_exists('str_regex_pos')) {
    function str_regex_pos($string, $regex)
    {
        preg_match($regex, $string, $matches, PREG_OFFSET_CAPTURE);
        return isset($matches[0]) ? $matches[0][1] : false;
    }
}

if (!function_exists('finance_round_value')) {
    function get_decimal_qty($value)
    {
        $value = (string) $value;
        $formattedParts = explode('.', $value);

        if (isset($formattedParts[1])) {
            return strlen($formattedParts[1]);
        }

        return 0;
    }
}

if (!function_exists('finance_round_value')) {
    function finance_round_value($value, $decimalsToShow, $roundingMode)
    {
        $multiplier = pow(10, $decimalsToShow);

        switch ($roundingMode) {
            case 'truncate':
                return floor($value * $multiplier) / $multiplier;
            case 'ceiling':
                return ceil($value * $multiplier) / $multiplier;
            case 'floor':
                return floor($value * $multiplier) / $multiplier;
            case 'round':
            default:
                return round($value, $decimalsToShow);
        }
    }
}

if (!function_exists('finance_ensure_decimals')) {
    function finance_ensure_decimals($formatted, $minDecimals)
    {
        $formattedParts = explode('.', $formatted);

        $decimal = rtrim($formattedParts[1] ?? '', '0');
        if (strlen($decimal) < $minDecimals) {
            $decimal = str_pad($decimal, $minDecimals, '0');
        }

        return $formattedParts[0] . '.' . $decimal;
    }
}
