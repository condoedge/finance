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
        try {
            $transliterator = Transliterator::createFromRules(
                ':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;',
                Transliterator::FORWARD
            );

            $value = $transliterator->transliterate($value);
        } catch (\Exception $e) {
            // Remove accents using character mapping
            $accents = [
                'À' => 'A',
                'Á' => 'A',
                'Â' => 'A',
                'Ã' => 'A',
                'Ä' => 'A',
                'Å' => 'A',
                'à' => 'a',
                'á' => 'a',
                'â' => 'a',
                'ã' => 'a',
                'ä' => 'a',
                'å' => 'a',
                'È' => 'E',
                'É' => 'E',
                'Ê' => 'E',
                'Ë' => 'E',
                'è' => 'e',
                'é' => 'e',
                'ê' => 'e',
                'ë' => 'e',
                'Ì' => 'I',
                'Í' => 'I',
                'Î' => 'I',
                'Ï' => 'I',
                'ì' => 'i',
                'í' => 'i',
                'î' => 'i',
                'ï' => 'i',
                'Ò' => 'O',
                'Ó' => 'O',
                'Ô' => 'O',
                'Õ' => 'O',
                'Ö' => 'O',
                'ò' => 'o',
                'ó' => 'o',
                'ô' => 'o',
                'õ' => 'o',
                'ö' => 'o',
                'Ù' => 'U',
                'Ú' => 'U',
                'Û' => 'U',
                'Ü' => 'U',
                'ù' => 'u',
                'ú' => 'u',
                'û' => 'u',
                'ü' => 'u',
                'Ý' => 'Y',
                'ý' => 'y',
                'ÿ' => 'y',
                'Ñ' => 'N',
                'ñ' => 'n',
                'Ç' => 'C',
                'ç' => 'c',
                'Æ' => 'AE',
                'æ' => 'ae',
                'Œ' => 'OE',
                'œ' => 'oe'
            ];

            $value = strtr($value, $accents);
        }

        // Remove pipe characters and trim
        return trim(str_replace('|', '', $value));
    }
}
