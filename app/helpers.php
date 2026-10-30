<?php

if (! function_exists('money')) {
    /** 1650 → « 1 650 DH » */
    function money(int|float|null $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' DH';
    }
}

if (! function_exists('normalize_phone')) {
    /** « +212 6 12-34-56-78 » → « 0612345678 » */
    function normalize_phone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '00212')) {
            $digits = '0'.substr($digits, 5);
        } elseif (str_starts_with($digits, '212') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits;
    }
}

if (! function_exists('format_phone')) {
    /** « 0612345678 » → « 06 12 34 56 78 » */
    function format_phone(?string $phone): string
    {
        $digits = normalize_phone($phone);

        return strlen($digits) === 10 ? trim(chunk_split($digits, 2, ' ')) : (string) $phone;
    }
}
