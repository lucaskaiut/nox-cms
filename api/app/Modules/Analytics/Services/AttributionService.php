<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Lead\Models\Lead;
use App\Modules\Post\Models\Post;

/**
 * Descobre de qual post do blog o lead veio: pelo slug enviado pelo site ou
 * pelo caminho da página de entrada (landing_path), usando o padrão de URL dos
 * posts configurado (ex.: /blog/{slug}). Sem padrão, usa o último segmento.
 */
class AttributionService
{
    public function resolvePost(?string $slug, ?string $landingPath, ?string $clickPath = null): ?Post
    {
        $settings = CrmSettings::current();

        foreach ([$slug, $settings->slugFromPath($landingPath), $settings->slugFromPath($clickPath)] as $candidate) {
            if (blank($candidate)) continue;
            if ($post = Post::query()->where('slug', $candidate)->first()) {
                return $post;
            }
        }

        return null;
    }

    /** Reatribui leads sem post (ex.: depois de mudar o padrão de URL). */
    public function backfill(): int
    {
        $count = 0;
        Lead::query()->whereNull('post_id')->where(fn ($q) => $q->whereNotNull('landing_path')->orWhereNotNull('click_path'))
            ->chunkById(500, function ($leads) use (&$count) {
                foreach ($leads as $lead) {
                    if ($post = $this->resolvePost(null, $lead->landing_path, $lead->click_path)) {
                        $lead->forceFill(['post_id' => $post->id])->saveQuietly();
                        $count++;
                    }
                }
            });

        return $count;
    }
}
