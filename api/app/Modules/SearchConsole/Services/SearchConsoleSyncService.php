<?php

namespace App\Modules\SearchConsole\Services;

use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Post\Models\Post;
use App\Modules\SearchConsole\Models\PostSearchMetric;
use App\Modules\SearchConsole\Models\SearchConsoleAccount;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Importa cliques/impressões/posição por página e dia e associa cada página a
 * um post pelo padrão de URL. O Search Console tem atraso de ~2–3 dias, por
 * isso cada sincronização regrava a janela inteira.
 */
class SearchConsoleSyncService
{
    public function sync(int $tenantId, int $days = 90): int
    {
        $account = SearchConsoleAccount::query()->forTenant($tenantId)->first();
        if (! $account?->is_active || ! $account->isConfigured()) {
            return 0;
        }

        try {
            $count = $this->import($account, $tenantId, $days);
            $account->forceFill(['last_sync_at' => now(), 'last_error' => null])->save();

            return $count;
        } catch (Throwable $e) {
            $account->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 2000)])->save();
            throw $e;
        }
    }

    private function import(SearchConsoleAccount $account, int $tenantId, int $days): int
    {
        $start = now()->subDays($days)->toDateString();
        $end = now()->subDay()->toDateString();
        $rows = (new SearchConsoleClient($account))->pageMetrics($start, $end);

        $settings = CrmSettings::current($tenantId);
        $posts = Post::query()->forTenant($tenantId)->pluck('id', 'slug');

        // Agrega por post e dia (várias URLs podem apontar para o mesmo post).
        $agg = [];
        foreach ($rows as $row) {
            $slug = $settings->slugFromPath(parse_url($row['page'], PHP_URL_PATH));
            $postId = $slug ? ($posts[$slug] ?? null) : null;
            if (! $postId) continue;

            $key = $postId.'|'.$row['date'];
            $agg[$key] ??= ['post_id' => $postId, 'date' => $row['date'], 'clicks' => 0, 'impressions' => 0, 'pos_weight' => 0.0];
            $agg[$key]['clicks'] += $row['clicks'];
            $agg[$key]['impressions'] += $row['impressions'];
            $agg[$key]['pos_weight'] += $row['position'] * max($row['impressions'], 1);
        }

        DB::transaction(function () use ($tenantId, $start, $end, $agg) {
            PostSearchMetric::query()->forTenant($tenantId)->whereBetween('date', [$start, $end])->delete();
            foreach (array_chunk(array_values($agg), 500) as $chunk) {
                PostSearchMetric::query()->insert(array_map(fn ($r) => [
                    'tenant_id' => $tenantId,
                    'post_id' => $r['post_id'],
                    'date' => $r['date'],
                    'clicks' => $r['clicks'],
                    'impressions' => $r['impressions'],
                    'position' => round($r['pos_weight'] / max($r['impressions'], 1), 2),
                ], $chunk));
            }
        });

        return count($agg);
    }
}
