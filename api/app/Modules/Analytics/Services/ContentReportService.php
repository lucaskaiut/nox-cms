<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Post\Models\Post;
use App\Modules\SearchConsole\Models\PostSearchMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Desempenho do conteúdo pelo que importa: leads e vendas por post (e por
 * categoria/tag), cruzados com a busca orgânica do Search Console. Também
 * aponta posts para atualizar e sugere links internos.
 */
class ContentReportService
{
    private const STOPWORDS = [
        'para', 'como', 'qual', 'quais', 'sobre', 'entre', 'mais', 'menos', 'muito', 'pela', 'pelo', 'pelos', 'pelas',
        'esse', 'essa', 'este', 'esta', 'isso', 'isto', 'seus', 'suas', 'quando', 'onde', 'porque', 'sem', 'com',
        'uma', 'umas', 'uns', 'dos', 'das', 'nos', 'nas', 'que', 'por', 'sua', 'seu', 'ser', 'ter', 'voce', 'guia',
        'completo', 'dicas', 'passo', 'tudo', 'saber', 'precisa', 'fazer', 'melhor', 'melhores', 'ano', '2024', '2025', '2026',
    ];

    /** Linhas por post publicado no período, ordenadas por vendas e leads. */
    public function posts(Carbon $from, Carbon $to): Collection
    {
        $leads = $this->leadsByPost($from, $to);
        [$search, $prevSearch] = $this->searchByPost($from, $to);

        return Post::query()->with(['categories:id,name', 'tags:id,name'])
            ->where('status', 'published')
            ->get(['id', 'uuid', 'title', 'slug', 'published_at', 'updated_at', 'cta_offer'])
            ->map(function (Post $post) use ($leads, $search, $prevSearch) {
                $l = $leads[$post->id] ?? collect();
                $s = $search[$post->id] ?? null;
                $p = $prevSearch[$post->id] ?? null;
                $clicks = (int) ($s->clicks ?? 0);

                return [
                    'id' => $post->uuid,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'published_at' => $post->published_at?->toIso8601String(),
                    'updated_at' => $post->updated_at?->toIso8601String(),
                    'cta_offer' => $post->cta_offer,
                    'categories' => $post->categories->pluck('name')->values(),
                    'tags' => $post->tags->pluck('name')->values(),
                    'leads' => $l->count(),
                    'qualified' => $l->whereNotNull('qualified_at')->count(),
                    'won' => $l->where('status', LeadStatus::WON)->count(),
                    'revenue' => round((float) $l->where('status', LeadStatus::WON)->sum('conversion_value'), 2),
                    'clicks' => $s ? $clicks : null,
                    'impressions' => $s ? (int) $s->impressions : null,
                    'position' => $s && $s->impressions > 0 ? round((float) $s->pos_weight / $s->impressions, 1) : null,
                    'clicks_previous' => $p ? (int) $p->clicks : null,
                    'lead_rate' => $clicks > 0 ? round($l->count() / $clicks * 100, 2) : null,
                ];
            })
            ->sortBy([['won', 'desc'], ['leads', 'desc'], ['clicks', 'desc']])
            ->values();
    }

    /** Somatório por categoria e por tag (um post pode contar em várias). */
    public function groups(Collection $rows): array
    {
        $group = function (string $key) use ($rows) {
            $out = [];
            foreach ($rows as $row) {
                foreach ($row[$key] as $name) {
                    $out[$name] ??= ['name' => $name, 'posts' => 0, 'leads' => 0, 'won' => 0, 'revenue' => 0.0, 'clicks' => 0];
                    $out[$name]['posts']++;
                    $out[$name]['leads'] += $row['leads'];
                    $out[$name]['won'] += $row['won'];
                    $out[$name]['revenue'] += $row['revenue'];
                    $out[$name]['clicks'] += (int) $row['clicks'];
                }
            }

            return collect($out)->sortBy([['won', 'desc'], ['leads', 'desc']])->values()->all();
        };

        return ['categories' => $group('categories'), 'tags' => $group('tags')];
    }

    /** Posts que merecem atualização: antigos ou perdendo tráfego. */
    public function stalePosts(): Collection
    {
        $months = CrmSettings::current()->stale_post_months;
        $now = now();
        [$recent, $before] = $this->searchByPost($now->copy()->subDays(28), $now);
        $leads = $this->leadsByPost($now->copy()->subDays(180), $now);

        return Post::query()->where('status', 'published')->get(['id', 'uuid', 'title', 'slug', 'published_at', 'updated_at'])
            ->map(function (Post $post) use ($months, $recent, $before, $leads) {
                $reasons = [];
                $lastTouch = $post->updated_at ?? $post->published_at;
                if ($lastTouch && $lastTouch->lt(now()->subMonths($months))) {
                    $reasons[] = ['type' => 'old', 'label' => 'Sem atualização há '.(int) $lastTouch->diffInMonths(now()).' meses'];
                }
                $r = (int) ($recent[$post->id]->clicks ?? 0);
                $b = (int) ($before[$post->id]->clicks ?? 0);
                if ($b >= 20 && $r < $b * 0.7) {
                    $reasons[] = ['type' => 'traffic_drop', 'label' => sprintf('Cliques caíram %d%% (28 dias)', round((1 - $r / $b) * 100))];
                }
                $impressions = (int) ($recent[$post->id]->impressions ?? 0);
                $position = $impressions > 0 ? $recent[$post->id]->pos_weight / $impressions : null;
                if ($position !== null && $position > 8 && $position <= 20 && $impressions >= 100) {
                    $reasons[] = ['type' => 'striking_distance', 'label' => sprintf('Posição média %.1f: perto da 1ª página', $position)];
                }
                if ($r >= 50 && ($leads[$post->id] ?? collect())->isEmpty()) {
                    $reasons[] = ['type' => 'no_leads', 'label' => 'Tem tráfego, mas não gerou leads (revise a CTA)'];
                }
                if ($reasons === []) return null;

                return [
                    'id' => $post->uuid, 'title' => $post->title, 'slug' => $post->slug,
                    'updated_at' => $post->updated_at?->toIso8601String(), 'reasons' => $reasons,
                    'clicks_28d' => $r, 'clicks_prev_28d' => $b,
                ];
            })->filter()->sortByDesc(fn ($p) => count($p['reasons']))->values();
    }

    /** Desempenho detalhado de um post (ficha no editor). */
    public function post(Post $post, int $days = 90): array
    {
        $since = now()->subDays($days);
        $leads = $post->leads()->where('status', '!=', LeadStatus::SPAM->value)->get();
        $metrics = PostSearchMetric::query()->where('post_id', $post->id)->where('date', '>=', $since->toDateString())->orderBy('date')->get();
        $impr = $metrics->sum('impressions');

        return [
            'leads' => $leads->count(),
            'qualified' => $leads->whereNotNull('qualified_at')->count(),
            'won' => $leads->where('status', LeadStatus::WON)->count(),
            'revenue' => round((float) $leads->where('status', LeadStatus::WON)->sum('conversion_value'), 2),
            'offers' => $leads->groupBy(fn ($l) => $l->cta ?: 'Sem oferta')->map(fn ($g, $k) => ['offer' => $k, 'leads' => $g->count()])->values(),
            'recent_leads' => $leads->sortByDesc('created_at')->take(5)->map(fn (Lead $l) => [
                'id' => $l->uuid, 'ref' => $l->ref, 'name' => $l->name, 'status' => $l->status?->value,
                'status_label' => $l->status?->label(), 'created_at' => $l->created_at?->toIso8601String(),
            ])->values(),
            'search' => $metrics->isEmpty() ? null : [
                'days' => $days,
                'clicks' => (int) $metrics->sum('clicks'),
                'impressions' => (int) $impr,
                'position' => $impr > 0 ? round($metrics->sum(fn ($m) => $m->position * $m->impressions) / $impr, 1) : null,
                'series' => $metrics->map(fn ($m) => ['date' => $m->date->toDateString(), 'clicks' => $m->clicks, 'impressions' => $m->impressions])->values(),
            ],
        ];
    }

    /**
     * Links internos: posts para os quais este deveria apontar e posts que
     * deveriam apontar para este. Pontua por tags/categorias em comum, palavras
     * do título e se o destino converte bem.
     */
    public function linkSuggestions(Post $post, int $limit = 6): array
    {
        $post->loadMissing(['tags:id', 'categories:id']);
        $tags = $post->tags->pluck('id');
        $cats = $post->categories->pluck('id');
        $tokens = $this->tokens($post->title.' '.$post->meta_keywords);
        $content = Str::lower(Str::ascii(strip_tags((string) $post->content)));
        $html = (string) $post->content;
        $leadCounts = Lead::query()->whereNotNull('post_id')->where('status', '!=', LeadStatus::SPAM->value)
            ->selectRaw('post_id, count(*) as total')->groupBy('post_id')->pluck('total', 'post_id');
        $settings = CrmSettings::current();

        $candidates = Post::query()->with(['tags:id', 'categories:id'])->where('status', 'published')->whereKeyNot($post->id)
            ->get(['id', 'uuid', 'title', 'slug', 'content', 'meta_keywords'])
            ->map(function (Post $other) use ($tags, $cats, $tokens, $content, $html, $leadCounts, $post, $settings) {
                $otherTokens = $this->tokens($other->title.' '.$other->meta_keywords);
                $sharedTokens = array_values(array_intersect($tokens, $otherTokens));
                $mentions = count(array_filter($otherTokens, fn ($t) => str_contains($content, $t)));
                $score = 3 * $tags->intersect($other->tags->pluck('id'))->count()
                    + 2 * $cats->intersect($other->categories->pluck('id'))->count()
                    + 1.5 * count($sharedTokens)
                    + 0.5 * min($mentions, 4)
                    + log1p((int) ($leadCounts[$other->id] ?? 0));

                return [
                    'id' => $other->uuid,
                    'title' => $other->title,
                    'slug' => $other->slug,
                    'url' => $settings->postUrl($other->slug),
                    'score' => round($score, 2),
                    'leads' => (int) ($leadCounts[$other->id] ?? 0),
                    'shared_terms' => array_slice($sharedTokens, 0, 5),
                    'already_linked' => $this->linksTo($html, $other->slug),
                    'links_here' => $this->linksTo((string) $other->content, $post->slug),
                ];
            })
            ->filter(fn ($c) => $c['score'] >= 1.5)
            ->sortByDesc('score')->values();

        return [
            'link_to' => $candidates->take($limit)->values(),
            'link_from' => $candidates->where('links_here', false)->take($limit)->values(),
        ];
    }

    /** Resumo para o guia editorial da IA: o que converte e o que não. */
    public function insightsForAi(): array
    {
        $rows = $this->posts(now()->subDays(180), now());
        $groups = $this->groups($rows);
        $offers = Lead::query()->where('created_at', '>=', now()->subDays(180))->whereNotNull('cta')
            ->where('status', '!=', LeadStatus::SPAM->value)->get(['cta', 'status', 'qualified_at'])
            ->groupBy('cta')->map(fn ($g, $cta) => [
                'offer' => $cta, 'leads' => $g->count(),
                'qualified' => $g->whereNotNull('qualified_at')->count(), 'won' => $g->where('status', LeadStatus::WON)->count(),
            ])->sortByDesc('won')->values()->take(5);
        $pick = fn (array $r) => ['title' => $r['title'], 'slug' => $r['slug'], 'leads' => $r['leads'], 'won' => $r['won'], 'clicks' => $r['clicks']];

        return [
            'period_days' => 180,
            'note' => 'Priorize temas e categorias que geram leads qualificados e vendas, não só tráfego. Termine cada post com a oferta que mais converte para o tema.',
            'top_posts' => $rows->filter(fn ($r) => $r['leads'] > 0)->take(5)->map($pick)->values(),
            'top_categories' => collect($groups['categories'])->filter(fn ($g) => $g['leads'] > 0)->take(5)->map(fn ($g) => ['name' => $g['name'], 'leads' => $g['leads'], 'won' => $g['won']])->values(),
            'top_tags' => collect($groups['tags'])->filter(fn ($g) => $g['leads'] > 0)->take(8)->map(fn ($g) => ['name' => $g['name'], 'leads' => $g['leads'], 'won' => $g['won']])->values(),
            'best_offers' => $offers,
            'traffic_without_leads' => $rows->filter(fn ($r) => ($r['clicks'] ?? 0) >= 50 && $r['leads'] === 0)->sortByDesc('clicks')->take(5)->map($pick)->values(),
        ];
    }

    private function leadsByPost(Carbon $from, Carbon $to): Collection
    {
        return Lead::query()->whereNotNull('post_id')->where('status', '!=', LeadStatus::SPAM->value)
            ->whereBetween('created_at', [$from, $to])->get(['id', 'post_id', 'status', 'qualified_at', 'conversion_value', 'cta'])
            ->groupBy('post_id');
    }

    /** Totais de busca por post no período e no período anterior de mesmo tamanho. */
    private function searchByPost(Carbon $from, Carbon $to): array
    {
        $length = max(1, (int) round($from->diffInHours($to) / 24));
        $query = fn (string $a, string $b) => PostSearchMetric::query()->whereBetween('date', [$a, $b])
            ->selectRaw('post_id, sum(clicks) as clicks, sum(impressions) as impressions, sum(position * impressions) as pos_weight')
            ->groupBy('post_id')->get()->keyBy('post_id');

        return [
            $query($from->toDateString(), $to->toDateString()),
            $query($from->copy()->subDays($length)->toDateString(), $from->copy()->subDay()->toDateString()),
        ];
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text))) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => strlen($w) >= 4 && ! in_array($w, self::STOPWORDS, true))));
    }

    private function linksTo(string $html, string $slug): bool
    {
        return (bool) preg_match('#href=["\'][^"\']*/'.preg_quote($slug, '#').'/?(["\'?\#])#i', $html);
    }
}
