<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Lead\Http\Resources\LeadResource;
use App\Modules\Lead\Models\Lead;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Http\Resources\ConversationResource;
use App\Modules\WhatsApp\Http\Resources\MessageResource;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use App\Modules\WhatsApp\Support\TemplateDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Caixa de entrada do WhatsApp no painel. */
class ConversationController extends ApiController
{
    public function __construct(private readonly WhatsAppService $service) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search'));
        $digits = preg_replace('/\D/', '', $search);

        $conversations = WhatsAppConversation::query()
            ->with('lead.stage')
            ->when($request->boolean('unread'), fn ($q) => $q->where('unread_count', '>', 0))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('contact_name', 'like', "%{$search}%")
                ->when($digits !== '', fn ($q) => $q->orWhere('wa_id', 'like', "%{$digits}%"))
                ->orWhereHas('lead', fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")->orWhere('ref', strtoupper($search)))))
            ->orderByRaw('last_message_at IS NULL')->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 30), 1), 100));

        return $this->paginated(ConversationResource::collection($conversations));
    }

    public function unread(): JsonResponse
    {
        return $this->success([
            'conversations' => WhatsAppConversation::query()->where('unread_count', '>', 0)->count(),
            'messages' => (int) WhatsAppConversation::query()->sum('unread_count'),
        ]);
    }

    public function show(WhatsAppConversation $conversation): JsonResponse
    {
        return $this->success(ConversationResource::make($conversation->load('lead.stage')));
    }

    /** ?before=<id> carrega mensagens antigas; ?after=<id> só as novas (polling). */
    public function messages(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $cursor = fn (string $param) => $request->filled($param)
            ? WhatsAppMessage::query()->where('uuid', $request->string($param))->value('id')
            : null;
        $before = $cursor('before');
        $after = $cursor('after');

        $query = $conversation->messages()->with('user');
        if ($after) {
            $messages = $query->where('id', '>', $after)->orderBy('id')->limit(200)->get();
        } else {
            $messages = $query->when($before, fn ($q) => $q->where('id', '<', $before))
                ->orderByDesc('id')->limit(50)->get()->reverse()->values();
        }

        // Status de entrega das últimas enviadas mudam depois do envio.
        $recentOutgoing = $conversation->messages()->where('direction', WhatsAppMessage::OUT)
            ->latest('id')->limit(20)->get(['uuid', 'status', 'error']);

        return response()->json([
            'success' => true,
            'data' => MessageResource::collection($messages),
            'statuses' => $recentOutgoing->map(fn ($m) => ['id' => $m->uuid, 'status' => $m->status, 'error' => $m->error]),
            'has_more' => ! $after && $messages->count() === 50,
            'conversation' => ConversationResource::make($conversation->fresh('lead.stage')),
        ]);
    }

    public function read(WhatsAppConversation $conversation): JsonResponse
    {
        $this->service->markRead($conversation);

        return $this->success(ConversationResource::make($conversation->fresh('lead.stage')));
    }

    public function send(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4096']]);
        $message = $this->service->sendText($conversation, $data['body'], $request->user());

        return $this->created(MessageResource::make($message->load('user')));
    }

    public function sendTemplate(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:512'],
            'language' => ['required', 'string', 'max:15'],
            'header' => ['nullable', 'array'], 'header.*' => ['nullable', 'string', 'max:60'],
            'body' => ['nullable', 'array'], 'body.*' => ['nullable', 'string', 'max:1024'],
        ]);
        $message = $this->service->sendTemplate(
            $conversation, $data['name'], $data['language'], $data['header'] ?? [], $data['body'] ?? [], $request->user(),
        );

        return $this->created(MessageResource::make($message->load('user')));
    }

    public function templates(Request $request): JsonResponse
    {
        return $this->success($this->service->templates($request->boolean('fresh'))
            ->map(fn (TemplateDefinition $t) => $t->toArray())->values());
    }

    public function createLead(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $lead = $this->service->createLeadFromConversation($conversation->load('lead'), $request->user());

        return $this->created(LeadResource::make($lead->load(['stage', 'conversation'])), 'Lead adicionado ao funil.');
    }

    public function startForLead(Lead $lead): JsonResponse
    {
        return $this->success(ConversationResource::make($this->service->startForLead($lead)->load('lead.stage')));
    }

    public function media(WhatsAppMessage $message): Response
    {
        abort_unless($message->hasMedia(), 404);
        $media = $this->service->client()->downloadMedia($message->media_id);

        return response($media['content'], 200, [
            'Content-Type' => $media['mime'] ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="'.addslashes($message->media_filename ?: 'arquivo').'"',
        ]);
    }
}
