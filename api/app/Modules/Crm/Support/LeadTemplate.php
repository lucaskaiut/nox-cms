<?php

namespace App\Modules\Crm\Support;

use App\Modules\Lead\Models\Lead;

/** Variáveis aceitas nos textos de e-mail: {{nome}}, {{primeiro_nome}}, {{empresa}}, {{ref}}, {{oferta}}. */
final class LeadTemplate
{
    public const VARIABLES = ['nome', 'primeiro_nome', 'empresa', 'ref', 'oferta'];

    public static function render(string $text, Lead $lead): string
    {
        $name = trim((string) $lead->name);
        $values = [
            'nome' => $name !== '' ? $name : 'tudo bem',
            'primeiro_nome' => $name !== '' ? explode(' ', $name)[0] : 'tudo bem',
            'empresa' => (string) ($lead->company ?? ''),
            'ref' => (string) $lead->ref,
            'oferta' => (string) ($lead->cta ?? ''),
        ];

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => $values[$m[1]] ?? $m[0], $text);
    }
}
