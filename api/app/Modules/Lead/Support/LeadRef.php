<?php

namespace App\Modules\Lead\Support;

/**
 * Código curto do lead (ex.: NX-7K4P2Q), no mesmo formato gerado pelo site:
 * sem caracteres ambíguos (0/O, 1/I), fácil de ler numa conversa.
 */
final class LeadRef
{
    public const PATTERN = '/NX-[2-9A-HJ-NP-Z]{6}/';

    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return "NX-{$code}";
    }

    /** Primeiro código encontrado num texto livre (mensagem do WhatsApp). */
    public static function find(?string $text): ?string
    {
        if ($text === null || ! preg_match(self::PATTERN, strtoupper($text), $match)) {
            return null;
        }

        return $match[0];
    }
}
