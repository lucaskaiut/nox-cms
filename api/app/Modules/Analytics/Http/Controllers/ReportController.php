<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Services\ContentReportService;
use App\Modules\Analytics\Services\FunnelReportService;
use App\Modules\Post\Models\Post;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends ApiController
{
    public function funnel(Request $request, FunnelReportService $service): JsonResponse
    {
        [$from, $to] = $this->period($request, 30);

        return $this->success($service->summary($from, $to));
    }

    public function content(Request $request, ContentReportService $service): JsonResponse
    {
        [$from, $to] = $this->period($request, 90);
        $rows = $service->posts($from, $to);

        return $this->success([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'posts' => $rows,
            ...$service->groups($rows),
        ]);
    }

    public function stalePosts(ContentReportService $service): JsonResponse
    {
        return $this->success($service->stalePosts());
    }

    public function postPerformance(Post $post, ContentReportService $service): JsonResponse
    {
        $this->authorize('view', $post);

        return $this->success($service->post($post));
    }

    public function linkSuggestions(Post $post, ContentReportService $service): JsonResponse
    {
        $this->authorize('view', $post);

        return $this->success($service->linkSuggestions($post));
    }

    /** Período em datas locais (from/to = YYYY-MM-DD), convertido para UTC. */
    private function period(Request $request, int $defaultDays): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $tz = (string) config('leads.google_ads.timezone');
        $to = $request->filled('to') ? Carbon::parse($request->string('to'), $tz) : now($tz);
        $from = $request->filled('from') ? Carbon::parse($request->string('from'), $tz) : $to->copy()->subDays($defaultDays - 1);

        if ($from->diffInDays($to) > 730) {
            $from = $to->copy()->subDays(730);
        }

        return [$from->startOfDay()->utc(), $to->endOfDay()->utc()];
    }
}
