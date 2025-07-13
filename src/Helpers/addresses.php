<?php

if (!function_exists('normalizeCountryCode')) {
    function normalizeCountryCode(string $country): string
    {
        $country = sanitizeString(strtolower($country));
        
        $mapping = [
            'canada' => 'CA',
            'united states' => 'US',
            'united states of america' => 'US',
            'usa' => 'US',
            'mexico' => 'MX',
            'united kingdom' => 'GB',
            'uk' => 'GB',
        ];
        
        return $mapping[$country] ?? strtoupper(substr($country, 0, 2));
    }
}