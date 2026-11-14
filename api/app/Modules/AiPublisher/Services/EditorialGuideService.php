<?php

namespace App\Modules\AiPublisher\Services;

use App\Modules\AiPublisher\Models\AiPublisherSettings;
use App\Modules\Analytics\Services\ContentReportService;
use Throwable;

class EditorialGuideService
{
    public function __construct(private readonly ContentReportService $content) {}

    /** Guia editorial + o que o CRM mostra que converte (temas, ofertas, posts). */
    public function get(): array
    {
        return $this->base() + ['performance_insights' => $this->insights()];
    }

    private function insights(): ?array
    {
        try {
            return $this->content->insightsForAi();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function base(): array
    {
        $settings = AiPublisherSettings::query()->first();

        if ($settings) {
            return [
                'company' => $settings->company ?? config('ai-publisher.editorial.company', ''),
                'audience' => $settings->audience ?? config('ai-publisher.editorial.audience', []),
                'tone' => $settings->tone ?? config('ai-publisher.editorial.tone', 'professional'),
                'content_rules' => $settings->content_rules ?? config('ai-publisher.editorial.content_rules', []),
            ];
        }

        return [
            'company' => config('ai-publisher.editorial.company', ''),
            'audience' => config('ai-publisher.editorial.audience', []),
            'tone' => config('ai-publisher.editorial.tone', 'professional'),
            'content_rules' => config('ai-publisher.editorial.content_rules', []),
        ];
    }
}
