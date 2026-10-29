<?php

namespace App\Modules\Lead\Http\Controllers;

use App\Modules\Lead\Http\Requests\StoreLeadRequest;
use App\Modules\Lead\Http\Requests\UpdateLeadRequest;
use App\Modules\Lead\Http\Resources\LeadResource;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Services\LeadService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class LeadController extends ApiController
{
    public function __construct(private readonly LeadService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        return $this->paginated(LeadResource::collection($this->service->paginate($request->only(['status', 'ref']), (int) $request->integer('per_page', 15))));
    }

    public function show(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        return $this->success(LeadResource::make($lead));
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $this->authorize('create', Lead::class);

        return $this->created(LeadResource::make($this->service->register($request->validated())), 'Lead registrado com sucesso.');
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        return $this->success(LeadResource::make($this->service->update($lead, $request->validated())), 'Lead atualizado com sucesso.');
    }

    public function exportGoogleAds(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);
        $request->validate(['since' => ['nullable', 'date']]);
        $since = $request->filled('since') ? Carbon::parse($request->string('since')) : null;
        $csv = $this->service->googleAdsCsv($this->service->qualifiedForGoogleAds($since));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="google-ads-leads-qualificados.csv"',
        ]);
    }
}
