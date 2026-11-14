<?php

namespace App\Modules\Nurture\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Lead\Models\Lead;
use App\Modules\Nurture\Models\NurtureEnrollment;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Descadastro público (link assinado no rodapé dos e-mails, LGPD). */
class UnsubscribeController extends Controller
{
    public function show(Request $request, string $lead): Response
    {
        $model = $this->lead($lead);

        return response()->view('unsubscribe', [
            'company' => Tenant::query()->find($model->tenant_id)?->name,
            'done' => $model->email_opt_out_at !== null,
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, string $lead): Response
    {
        $model = $this->lead($lead);
        if ($model->email_opt_out_at === null) {
            $model->forceFill(['email_opt_out_at' => now()])->saveQuietly();
            NurtureEnrollment::query()->withoutTenancy()->where('lead_id', $model->id)->where('status', NurtureEnrollment::ACTIVE)
                ->update(['status' => NurtureEnrollment::STOPPED, 'stopped_reason' => 'Descadastrado', 'next_run_at' => null]);
            LeadActivity::query()->create([
                'tenant_id' => $model->tenant_id, 'lead_id' => $model->id, 'type' => LeadActivity::NOTE,
                'body' => 'Descadastrou-se dos e-mails.', 'meta' => ['kind' => 'unsubscribe'],
            ]);
        }

        return response()->view('unsubscribe', [
            'company' => Tenant::query()->find($model->tenant_id)?->name,
            'done' => true,
            'action' => null,
        ]);
    }

    private function lead(string $uuid): Lead
    {
        return Lead::query()->withoutTenancy()->where('uuid', $uuid)->firstOrFail();
    }
}
