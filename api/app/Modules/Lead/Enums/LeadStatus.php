<?php

namespace App\Modules\Lead\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case QUALIFIED = 'qualified';
    case WON = 'won';
    case LOST = 'lost';
    case SPAM = 'spam';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Novo',
            self::QUALIFIED => 'Qualificado',
            self::WON => 'Fechado',
            self::LOST => 'Perdido',
            self::SPAM => 'Spam',
        };
    }

    /** Status que contam como lead qualificado para a conversão offline. */
    public function isQualified(): bool
    {
        return in_array($this, [self::QUALIFIED, self::WON], true);
    }
}
