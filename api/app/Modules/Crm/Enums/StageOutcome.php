<?php

namespace App\Modules\Crm\Enums;

use App\Modules\Lead\Enums\LeadStatus;

/**
 * O que uma etapa do funil significa para relatórios e para o Google Ads.
 * As etapas são livres (nome, cor, ordem); o resultado é que liga a etapa ao
 * status do lead e, portanto, à conversão offline ("qualificado"/"fechado").
 */
enum StageOutcome: string
{
    case OPEN = 'open';
    case QUALIFIED = 'qualified';
    case WON = 'won';
    case LOST = 'lost';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Em andamento',
            self::QUALIFIED => 'Qualificado',
            self::WON => 'Ganho',
            self::LOST => 'Perdido',
        };
    }

    public function leadStatus(): LeadStatus
    {
        return match ($this) {
            self::OPEN => LeadStatus::NEW,
            self::QUALIFIED => LeadStatus::QUALIFIED,
            self::WON => LeadStatus::WON,
            self::LOST => LeadStatus::LOST,
        };
    }

    public static function fromLeadStatus(LeadStatus $status): ?self
    {
        return match ($status) {
            LeadStatus::NEW => self::OPEN,
            LeadStatus::QUALIFIED => self::QUALIFIED,
            LeadStatus::WON => self::WON,
            LeadStatus::LOST => self::LOST,
            LeadStatus::SPAM => null,
        };
    }
}
