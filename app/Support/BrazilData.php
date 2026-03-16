<?php

namespace App\Support;

final class BrazilData
{
    /**
     * @var list<string>
     */
    public const STATE_CODES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO',
    ];

    public static function normalizeState(mixed $value): string
    {
        return strtoupper(trim((string) ($value ?? '')));
    }

    public static function normalizeCep(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));
        $digits = is_string($digits) ? substr($digits, 0, 8) : '';

        if (strlen($digits) !== 8) {
            return trim((string) ($value ?? ''));
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }

    public static function normalizePhone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));
        $digits = is_string($digits) ? substr($digits, 0, 11) : '';

        if (strlen($digits) !== 11) {
            return trim((string) ($value ?? ''));
        }

        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
    }

    public static function normalizeCpfCnpj(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));
        $digits = is_string($digits) ? substr($digits, 0, 14) : '';

        if (strlen($digits) === 11) {
            return sprintf(
                '%s.%s.%s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 3),
                substr($digits, 9, 2),
            );
        }

        if (strlen($digits) === 14) {
            return sprintf(
                '%s.%s.%s/%s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 3),
                substr($digits, 5, 3),
                substr($digits, 8, 4),
                substr($digits, 12, 2),
            );
        }

        return trim((string) ($value ?? ''));
    }
}
