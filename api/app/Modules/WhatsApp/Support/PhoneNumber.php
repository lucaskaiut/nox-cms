<?php

namespace App\Modules\WhatsApp\Support;

/**
 * Números no formato do WhatsApp (wa_id): só dígitos, com DDI.
 *
 * Celulares do Brasil podem chegar com ou sem o nono dígito (o wa_id de contas
 * antigas costuma vir sem ele), então a busca considera as duas formas.
 */
final class PhoneNumber
{
    /** Número digitado (site/painel) → wa_id. Sem DDI, assume Brasil. */
    public static function toWaId(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }
        if (strlen($digits) <= 11) {
            $digits = '55'.ltrim($digits, '0');
        }

        return strlen($digits) <= 15 ? $digits : null;
    }

    /** @return list<string> formas equivalentes do mesmo número */
    public static function candidates(string $waId): array
    {
        $digits = preg_replace('/\D/', '', $waId);
        $candidates = [$digits];

        if (str_starts_with($digits, '55')) {
            $local = substr($digits, 4);
            $ddd = substr($digits, 2, 2);
            if (strlen($local) === 9 && $local[0] === '9') {
                $candidates[] = '55'.$ddd.substr($local, 1);
            } elseif (strlen($local) === 8 && in_array($local[0], ['6', '7', '8', '9'], true)) {
                $candidates[] = '55'.$ddd.'9'.$local;
            }
        }

        return array_values(array_unique($candidates));
    }

    /** wa_id → formato legível: +55 (41) 98829-7008 */
    public static function format(string $waId): string
    {
        $d = preg_replace('/\D/', '', $waId);
        if (str_starts_with($d, '55') && in_array(strlen($d), [12, 13], true)) {
            $local = substr($d, 4);

            return sprintf('+55 (%s) %s-%s', substr($d, 2, 2), substr($local, 0, -4), substr($local, -4));
        }

        return '+'.$d;
    }
}
