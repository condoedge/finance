<?php

if (!function_exists('integerArray')) {
    function integerArray(?array $array): array
    {
        if (is_null($array) || !is_array($array)) {
            return [];
        }

        return array_map('intval', $array);
    }
}

if (!function_exists('sanitizeString')) {
    function sanitizeString(string $value): string
    {
        // Remove accents
        $transliterator = Transliterator::createFromRules(
            ':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;',
            Transliterator::FORWARD
        );

        $value = $transliterator->transliterate($value);

        // Remove pipe characters and trim
        return trim(str_replace('|', '', $value));
    }
}
