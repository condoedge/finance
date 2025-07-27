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
        // Check if Transliterator class exists (requires php-intl extension)
        if (class_exists('Transliterator')) {
            try {
                $transliterator = \Transliterator::createFromRules(
                    ':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;',
                    \Transliterator::FORWARD
                );

                $value = $transliterator->transliterate($value);
            } catch (\Exception $e) {
                // Fallback to manual mapping if transliterator fails
                $value = removeAccentsManually($value);
            }
        } else {
            // Fallback to manual mapping if Transliterator class doesn't exist
            $value = removeAccentsManually($value);
        }

        // Remove pipe characters and trim
        return trim(str_replace('|', '', $value));
    }
}

if (!function_exists('removeAccentsManually')) {
    function removeAccentsManually(string $value): string
    {
        // Remove accents using comprehensive character mapping
        $accents = [
            // Latin A
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ą' => 'A', 'Ă' => 'A',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a',
            
            // Latin E
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ę' => 'E', 'Ě' => 'E', 'Ė' => 'E', 'Ĕ' => 'E',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ė' => 'e', 'ĕ' => 'e',
            
            // Latin I
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Ĩ' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ĩ' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i',
            
            // Latin O
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ō' => 'O', 'Ő' => 'O', 'Ŏ' => 'O',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'ŏ' => 'o',
            
            // Latin U  
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ū' => 'U', 'Ũ' => 'U', 'Ŭ' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'Ų' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ũ' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
            
            // Latin Y
            'Ý' => 'Y', 'Ÿ' => 'Y', 'Ŷ' => 'Y',
            'ý' => 'y', 'ÿ' => 'y', 'ŷ' => 'y',
            
            // Other Latin
            'Ñ' => 'N', 'ñ' => 'n', 'Ń' => 'N', 'ń' => 'n', 'Ň' => 'N', 'ň' => 'n', 'Ņ' => 'N', 'ņ' => 'n',
            'Ç' => 'C', 'ç' => 'c', 'Ć' => 'C', 'ć' => 'c', 'Ĉ' => 'C', 'ĉ' => 'c', 'Ċ' => 'C', 'ċ' => 'c', 'Č' => 'C', 'č' => 'c',
            'Ś' => 'S', 'ś' => 's', 'Ŝ' => 'S', 'ŝ' => 's', 'Ş' => 'S', 'ş' => 's', 'Š' => 'S', 'š' => 's',
            'Ž' => 'Z', 'ž' => 'z', 'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z',
            'Ð' => 'D', 'ð' => 'd', 'Đ' => 'D', 'đ' => 'd',
            'Þ' => 'TH', 'þ' => 'th',
            'ß' => 'ss',
            'Ł' => 'L', 'ł' => 'l',
            'Ŕ' => 'R', 'ŕ' => 'r', 'Ř' => 'R', 'ř' => 'r',
            'Ţ' => 'T', 'ţ' => 't', 'Ť' => 'T', 'ť' => 't',
            'Ŵ' => 'W', 'ŵ' => 'w',
            
            // Ligatures
            'Æ' => 'AE', 'æ' => 'ae',
            'Œ' => 'OE', 'œ' => 'oe',
            'Ĳ' => 'IJ', 'ĳ' => 'ij',
        ];

        // Special characters mapping
        $specialChars = [
            // Quote marks
            chr(226).chr(128).chr(152) => "'", // left single quote
            chr(226).chr(128).chr(153) => "'", // right single quote  
            chr(226).chr(128).chr(156) => '"', // left double quote
            chr(226).chr(128).chr(157) => '"', // right double quote
            // Dashes
            chr(226).chr(128).chr(147) => '-', // em dash
            chr(226).chr(128).chr(148) => '-', // en dash
            // Others
            chr(194).chr(160) => ' ', // non-breaking space
            chr(194).chr(173) => '', // soft hyphen
        ];

        // First, normalize using the mappings
        $value = strtr($value, array_merge($accents, $specialChars));
        
        // Additional cleanup: remove any character that is not letter, number, space, hyphen, apostrophe, period
        $value = preg_replace('/[^\p{L}\p{N}\s\-\'.]/u', '', $value);
        
        // Normalize multiple spaces to single space
        $value = preg_replace('/\s+/', ' ', $value);
        
        return $value;
    }
}
