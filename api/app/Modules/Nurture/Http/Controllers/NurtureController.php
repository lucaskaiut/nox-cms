<?php

namespace App\Modules\Nurture\Http\Controllers;

use App\Modules\Crm\Mail\LeadEmail;
use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Crm\Support\LeadTemplate;
use App\Modules\Lead\Models\Lead;
use App\Modules\Nurture\Http\Resources\NurtureEnrollmentResource;
use App\Modules\Nurture\Http\Resources\NurtureSequenceResource;
use App\Modules\Nurture\Models\NurtureEnrollment;
use App\Modules\Nurture\Models\NurtureSequence;
use App\Modules\Nurture\Services\NurtureService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NurtureController extends ApiController
{
    public function __construct(private readonly NurtureService $service) {}

    public function index(): JsonResponse
    {
        $sequences = NurtureSequence::query()->with('triggerTag')->withCount([
            'steps',
            'enrollments as active_count' => fn ($q) => $q->where('status', NurtureEnrollment::ACTIVE),
            'enrollments as completed_count' => fn ($q) => $q->where('status', NurtureEnrollment::COMPLETED),
        ])->orderBy('name')->get();

        return $this->success(NurtureSequenceResource::collection($sequences));
    }

    public function show(NurtureSequence $sequence): JsonResponse
    {
        return $this->success(NurtureSequenceResource::make($sequence->load(['steps.post', 'triggerTag'])));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->created(NurtureSequenceResource::make($this->service->save(null, $this->validated($request))), 'Sequência criada.');
    }

    public function update(Request $request, NurtureSequence $sequence): JsonResponse
    {
        return $this->success(NurtureSequenceResource::make($this->service->save($sequence, $this->validated($request, false))), 'Sequência salva.');
    }

    public function destroy(NurtureSequence $sequence): JsonResponse
    {
        $sequence->delete();

        return $this->success(null, 'Sequência removida.');
    }

    public function enrollments(NurtureSequence $sequence): JsonResponse
    {
        $items = $sequence->enrollments()->with(['lead', 'sequence'])->latest('id')->limit(200)->get();

        return $this->success(NurtureEnrollmentResource::collection($items));
    }

    /** Envia um passo para o e-mail de quem está logado, com dados de exemplo. */
    public function test(Request $request, NurtureSequence $sequence): JsonResponse
    {
        $data = $request->validate(['step' => ['required', 'integer', 'min:0']]);
        $step = $sequence->steps()->with('post')->get()->values()[$data['step']] ?? null;
        $user = $request->user();
        if (! $step || ! $user instanceof User) {
            throw ValidationException::withMessages(['step' => 'Passo não encontrado.']);
        }

        $sample = new Lead(['name' => $user->name, 'company' => 'Empresa Exemplo', 'ref' => 'NX-EXEMPL', 'cta' => 'Oferta exemplo']);
        $settings = CrmSettings::current();
        $post = $step->post && ($url = $settings->postUrl($step->post->slug))
            ? ['title' => $step->post->title, 'excerpt' => $step->post->excerpt, 'url' => $url] : null;

        Mail::to($user->email)->send(new LeadEmail(
            subjectLine: '[Teste] '.LeadTemplate::render($step->subject, $sample),
            body: LeadTemplate::render($step->body, $sample),
            signature: $settings->email_signature,
            post: $post,
        ));

        return $this->success(null, "E-mail de teste enviado para {$user->email}.");
    }

    public function leadEnrollments(Lead $lead): JsonResponse
    {
        return $this->success(NurtureEnrollmentResource::collection($lead->enrollments()->with('sequence.steps')->latest('id')->get()));
    }

    public function enroll(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate(['sequence_id' => ['required', 'uuid']]);
        $sequence = NurtureSequence::query()->where('uuid', $data['sequence_id'])->firstOrFail();
        $user = $request->user() instanceof User ? $request->user() : null;

        $enrollment = $this->service->enroll($lead, $sequence, true, $user);

        return $this->created(NurtureEnrollmentResource::make($enrollment->load('sequence.steps')), 'Lead inscrito na sequência.');
    }

    public function stop(NurtureEnrollment $enrollment): JsonResponse
    {
        $this->service->stop($enrollment, 'Interrompida manualmente');

        return $this->success(NurtureEnrollmentResource::make($enrollment->refresh()->load('sequence.steps')), 'Sequência interrompida para este lead.');
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'trigger' => [$req, Rule::in([NurtureSequence::TRIGGER_MANUAL, NurtureSequence::TRIGGER_TAG, NurtureSequence::TRIGGER_LOST])],
            'trigger_tag_id' => ['nullable', 'uuid', 'required_if:trigger,tag'],
            'trigger_delay_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => [$creating ? 'present' : 'sometimes', 'array', 'max:20'],
            'steps.*.id' => ['nullable', 'uuid'],
            'steps.*.delay_days' => ['required', 'integer', 'min:0', 'max:365'],
            'steps.*.subject' => ['required', 'string', 'max:160'],
            'steps.*.body' => ['required', 'string', 'max:10000'],
            'steps.*.post_id' => ['nullable', 'uuid'],
        ], ['trigger_tag_id.required_if' => 'Escolha a tag que inicia a sequência.']);
    }
}
