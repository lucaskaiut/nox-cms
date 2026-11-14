<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Models\AdSpend;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Lançamentos de investimento em mídia (para custo por lead e ROAS). */
class AdSpendController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(AdSpend::query()->orderByDesc('period_start')->limit(200)->get()->map(fn ($s) => $this->present($s)));
    }

    public function store(Request $request): JsonResponse
    {
        $spend = AdSpend::query()->create($this->validated($request));

        return $this->created($this->present($spend), 'Investimento registrado.');
    }

    public function update(Request $request, AdSpend $spend): JsonResponse
    {
        $spend->fill($this->validated($request))->save();

        return $this->success($this->present($spend), 'Investimento atualizado.');
    }

    public function destroy(AdSpend $spend): JsonResponse
    {
        $spend->delete();

        return $this->success(null, 'Lançamento removido.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'channel' => ['required', Rule::in(array_keys(AdSpend::CHANNELS))],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function present(AdSpend $s): array
    {
        return [
            'id' => $s->uuid, 'channel' => $s->channel, 'channel_label' => AdSpend::CHANNELS[$s->channel] ?? $s->channel,
            'period_start' => $s->period_start->toDateString(), 'period_end' => $s->period_end->toDateString(),
            'amount' => (float) $s->amount, 'notes' => $s->notes,
        ];
    }
}
