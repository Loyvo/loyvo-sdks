<?php

namespace Loyvo;

final class Phone
{
    /**
     * A Sri Lankan mobile number as Loyvo stores it: "94771234567".
     * Accepts "0771234567", "771234567", "+94 77 123 4567", "0094-77-1234567".
     * Returns null when it is not a Sri Lankan mobile number.
     */
    public static function normalize(string $input): ?string
    {
        $d = (string) preg_replace('/[^\d+]/', '', $input);
        if (strpos($d, '+') === 0) {
            $d = substr($d, 1);
        }
        if (strpos($d, '00') === 0) {
            $d = substr($d, 2);
        }
        if (strpos($d, '94') === 0) {
            $d = substr($d, 2);
        } elseif (strpos($d, '0') === 0) {
            $d = substr($d, 1);
        }
        return preg_match('/^7\d{8}$/', $d) ? '94' . $d : null;
    }

    /** @internal */
    public static function newIdempotencyKey(): string
    {
        return 'loyvo-' . bin2hex(random_bytes(16));
    }
}
