<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Lead\Models\Lead;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private const SECRET = 'app-secret-123';
    private const PNID = '1111222233';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenantWithRoles();
        $this->forgetTenantContext();
    }

    private function configureAccount(array $overrides = []): WhatsAppAccount
    {
        return WhatsAppAccount::withoutTenancy()->create(array_merge([
            'tenant_id' => $this->tenant->id, 'phone_number_id' => self::PNID, 'waba_id' => '9999888877',
            'access_token' => 'EAAG-token', 'app_secret' => self::SECRET, 'verify_token' => 'verify-token-abcdefgh',
            'is_active' => true, 'verified_at' => now(),
        ], $overrides));
    }

    private function actingAsAdmin(): void
    {
        $this->forgetTenantContext();
        Sanctum::actingAs($this->createAdmin($this->tenant));
    }

    private function webhook(array $value, ?string $secret = self::SECRET)
    {
        $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => [[
            'id' => '9999888877',
            'changes' => [['field' => 'messages', 'value' => ['messaging_product' => 'whatsapp',
                'metadata' => ['display_phone_number' => '5541988297008', 'phone_number_id' => self::PNID]] + $value]],
        ]]]);
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($secret !== null) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $payload, $secret);
        }

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [], $headers, $payload);
        $this->forgetTenantContext();

        return $response;
    }

    private function inbound(string $from, string $text, string $id = 'wamid.IN1', string $name = 'Maria'): array
    {
        return [
            'contacts' => [['profile' => ['name' => $name], 'wa_id' => $from]],
            'messages' => [['from' => $from, 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]]],
        ];
    }

    private function siteLead(array $payload): void
    {
        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($this->tenant)->create(['token_hash' => ApiToken::hash($plain), 'permissions' => [Permission::LEAD_CREATE->value]]);
        $this->postJson('/api/leads', $payload, ['Authorization' => "Bearer {$plain}"])->assertCreated();
        $this->forgetTenantContext();
        $this->app['auth']->forgetGuards();
    }

    public function test_webhook_verification_handshake(): void
    {
        $this->configureAccount();

        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-token-abcdefgh&hub.challenge=12345')
            ->assertOk()->assertSee('12345');
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=errado&hub.challenge=12345')->assertForbidden();
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->configureAccount();

        $this->webhook($this->inbound('5541999990000', 'oi'), 'outro-segredo')->assertForbidden();
        $this->webhook($this->inbound('5541999990000', 'oi'), null)->assertForbidden();
        $this->assertSame(0, WhatsAppMessage::withoutTenancy()->count());
    }

    public function test_inbound_message_with_code_links_whatsapp_lead_and_is_idempotent(): void
    {
        $this->configureAccount();
        $this->siteLead(['ref' => 'NX-TZVXG4', 'channel' => 'whatsapp', 'gclid' => 'Cj0-abc']);

        $value = $this->inbound('5541999990000', 'Olá! Vim pelo site da Nox (código NX-TZVXG4)');
        $this->webhook($value)->assertOk();
        $this->webhook($value)->assertOk(); // reentrega

        $conversation = WhatsAppConversation::withoutTenancy()->first();
        $this->assertSame(1, WhatsAppMessage::withoutTenancy()->count());
        $this->assertSame(1, $conversation->unread_count);
        $this->assertSame('Maria', $conversation->contact_name);
        $this->assertTrue($conversation->windowOpen());

        $lead = Lead::withoutTenancy()->first();
        $this->assertSame($lead->id, $conversation->lead_id);
        $this->assertSame('Maria', $lead->name);
        $this->assertSame('+55 (41) 99999-0000', $lead->phone);
        $this->assertSame(['created', 'whatsapp_linked'], $lead->activities()->withoutGlobalScopes()->orderBy('id')->pluck('type')->all());
    }

    public function test_inbound_without_code_links_form_lead_by_phone_even_without_ninth_digit(): void
    {
        $this->configureAccount();
        $this->siteLead(['ref' => 'NX-FRM234', 'channel' => 'form', 'name' => 'Ana', 'phone' => '(41) 98829-7008', 'message' => 'Quero um ERP']);

        $this->webhook($this->inbound('554188297008', 'Oi, recebi o contato'))->assertOk();

        $this->assertSame(Lead::withoutTenancy()->value('id'), WhatsAppConversation::withoutTenancy()->value('lead_id'));
    }

    public function test_unknown_contact_stays_in_inbox_and_can_be_added_to_pipeline(): void
    {
        $this->configureAccount();
        $this->webhook($this->inbound('5511988887777', 'Bom dia, vocês fazem app?', 'wamid.X', 'Carlos'))->assertOk();
        $this->assertSame(0, Lead::withoutTenancy()->count());

        $this->actingAsAdmin();
        $id = $this->getJson('/api/whatsapp/conversations')->assertOk()->assertJsonPath('data.0.contact_name', 'Carlos')->json('data.0.id');
        $this->getJson('/api/whatsapp/unread')->assertJsonPath('data.messages', 1);

        $this->postJson("/api/whatsapp/conversations/{$id}/lead")->assertCreated()
            ->assertJsonPath('data.name', 'Carlos')->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.message', 'Bom dia, vocês fazem app?')->assertJsonPath('data.stage.name', 'Novo');
    }

    public function test_send_text_inside_window_and_status_updates(): void
    {
        $this->configureAccount();
        $this->webhook($this->inbound('5541999990000', 'oi'))->assertOk();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp', 'contacts' => [['input' => '5541999990000', 'wa_id' => '5541999990000']],
            'messages' => [['id' => 'wamid.OUT1']],
        ])]);

        $this->actingAsAdmin();
        $id = WhatsAppConversation::withoutTenancy()->value('uuid');
        $this->postJson("/api/whatsapp/conversations/{$id}/messages", ['body' => 'Olá Maria! Como podemos ajudar?'])
            ->assertCreated()->assertJsonPath('data.status', 'sent')->assertJsonPath('data.direction', 'out');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/'.self::PNID.'/messages')
            && $r['to'] === '5541999990000' && $r['text']['body'] === 'Olá Maria! Como podemos ajudar?'
            && $r->hasHeader('Authorization', 'Bearer EAAG-token'));

        $status = fn (string $s, array $extra = []) => ['statuses' => [array_merge(['id' => 'wamid.OUT1', 'status' => $s, 'timestamp' => '1', 'recipient_id' => '5541999990000'], $extra)]];
        $this->webhook($status('read'))->assertOk();
        $this->webhook($status('delivered'))->assertOk(); // atrasado: não rebaixa
        $this->assertSame('read', WhatsAppMessage::withoutTenancy()->where('wamid', 'wamid.OUT1')->value('status'));

        $this->actingAsAdmin();
        $this->getJson("/api/whatsapp/conversations/{$id}/messages")->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.1.status', 'read')->assertJsonPath('conversation.window_open', true);
    }

    public function test_text_outside_window_is_refused_and_template_is_sent(): void
    {
        $this->configureAccount();
        $this->siteLead(['ref' => 'NX-FRM234', 'channel' => 'form', 'name' => 'Ana', 'phone' => '(41) 98829-7008', 'message' => 'Quero um ERP']);
        Http::fake([
            'graph.facebook.com/*/message_templates*' => Http::response(['data' => [[
                'name' => 'retorno_contato', 'language' => 'pt_BR', 'status' => 'APPROVED', 'category' => 'UTILITY',
                'components' => [['type' => 'BODY', 'text' => 'Olá {{1}}, recebemos sua mensagem sobre {{2}}.']],
            ], [
                'name' => 'promo_imagem', 'language' => 'pt_BR', 'status' => 'APPROVED', 'category' => 'MARKETING',
                'components' => [['type' => 'HEADER', 'format' => 'IMAGE'], ['type' => 'BODY', 'text' => 'Oferta']],
            ]]]),
            'graph.facebook.com/*/messages' => Http::response([
                'contacts' => [['input' => '5541988297008', 'wa_id' => '554188297008']], 'messages' => [['id' => 'wamid.TPL1']],
            ]),
        ]);

        $this->actingAsAdmin();
        $leadId = $this->getJson('/api/leads')->json('data.0.id');
        $conversationId = $this->postJson("/api/leads/{$leadId}/whatsapp")->assertOk()
            ->assertJsonPath('data.wa_id', '5541988297008')->assertJsonPath('data.window_open', false)->json('data.id');

        $this->postJson("/api/whatsapp/conversations/{$conversationId}/messages", ['body' => 'oi'])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, '24 horas'));

        $templates = $this->getJson('/api/whatsapp/templates')->assertOk()->json('data');
        $this->assertSame(['1', '2'], $templates[1]['body_params']);
        $this->assertFalse($templates[0]['supported']);

        $this->postJson("/api/whatsapp/conversations/{$conversationId}/templates", ['name' => 'retorno_contato', 'language' => 'pt_BR', 'body' => ['1' => 'Ana']])
            ->assertUnprocessable();
        $this->postJson("/api/whatsapp/conversations/{$conversationId}/templates", [
            'name' => 'retorno_contato', 'language' => 'pt_BR', 'body' => ['1' => 'Ana', '2' => 'o ERP'],
        ])->assertCreated()->assertJsonPath('data.type', 'template')->assertJsonPath('data.body', 'Olá Ana, recebemos sua mensagem sobre o ERP.');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages') && ($r['template']['components'][0]['parameters'][1]['text'] ?? null) === 'o ERP');
        // A Meta devolveu o wa_id sem o nono dígito: a conversa passa a usá-lo.
        $this->assertSame('554188297008', WhatsAppConversation::withoutTenancy()->value('wa_id'));
    }

    public function test_account_settings_are_encrypted_masked_and_activation_subscribes_app(): void
    {
        $this->actingAsAdmin();

        $settings = $this->getJson('/api/whatsapp/account')->assertOk()->assertJsonPath('data.is_active', false)->json('data');
        $this->assertSame(40, strlen($settings['verify_token']));
        $this->assertStringEndsWith('/api/whatsapp/webhook', $settings['webhook_url']);

        $this->postJson('/api/whatsapp/account/activate')->assertUnprocessable();

        $this->putJson('/api/whatsapp/account', [
            'phone_number_id' => self::PNID, 'waba_id' => '9999888877', 'access_token' => 'EAAG-secret-token',
            'app_secret' => self::SECRET, 'verify_token' => $settings['verify_token'], 'graph_version' => 'v23.0',
        ])->assertOk()->assertJsonPath('data.has_access_token', true)->assertJsonMissing(['access_token' => 'EAAG-secret-token']);

        $raw = \DB::table('whatsapp_accounts')->value('access_token');
        $this->assertNotSame('EAAG-secret-token', $raw);

        Http::fake([
            'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true]),
            'graph.facebook.com/*' => Http::response(['display_phone_number' => '+55 41 98829-7008', 'verified_name' => 'Nox', 'quality_rating' => 'GREEN']),
        ]);
        $this->postJson('/api/whatsapp/account/activate')->assertOk()
            ->assertJsonPath('data.is_active', true)->assertJsonPath('data.verified_name', 'Nox');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/9999888877/subscribed_apps'));

        // Salvar de novo sem token mantém o atual (campo em branco).
        $this->putJson('/api/whatsapp/account', [
            'phone_number_id' => self::PNID, 'waba_id' => '9999888877', 'access_token' => '',
            'verify_token' => $settings['verify_token'], 'graph_version' => 'v23.0',
        ])->assertOk()->assertJsonPath('data.has_access_token', true)->assertJsonPath('data.is_active', true);
    }

    public function test_graph_errors_are_translated(): void
    {
        $this->configureAccount();
        $this->webhook($this->inbound('5541999990000', 'oi'))->assertOk();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Invalid OAuth access token']], 401)]);

        $this->actingAsAdmin();
        $id = WhatsAppConversation::withoutTenancy()->value('uuid');
        $this->postJson("/api/whatsapp/conversations/{$id}/messages", ['body' => 'oi'])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'Token de acesso inválido'));
        $this->assertSame(1, WhatsAppMessage::withoutTenancy()->count());
    }

    public function test_member_cannot_read_conversations_and_other_tenant_is_isolated(): void
    {
        $this->configureAccount();
        $this->webhook($this->inbound('5541999990000', 'oi'))->assertOk();
        $id = WhatsAppConversation::withoutTenancy()->value('uuid');

        Sanctum::actingAs($this->createMember($this->tenant));
        $this->getJson('/api/whatsapp/conversations')->assertForbidden();

        $other = $this->createTenantWithRoles();
        $this->forgetTenantContext();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->createAdmin($other));
        $this->getJson("/api/whatsapp/conversations/{$id}")->assertNotFound();
    }

    public function test_phone_number_helpers(): void
    {
        $this->assertSame('5541988297008', PhoneNumber::toWaId('(41) 98829-7008'));
        $this->assertSame('5541988297008', PhoneNumber::toWaId('+55 41 98829-7008'));
        $this->assertNull(PhoneNumber::toWaId('123'));
        $this->assertSame(['5541988297008', '554188297008'], PhoneNumber::candidates('5541988297008'));
        $this->assertSame(['554188297008', '5541988297008'], PhoneNumber::candidates('554188297008'));
        $this->assertSame(['554133334444'], PhoneNumber::candidates('554133334444'), 'fixo não ganha 9');
        $this->assertSame('+55 (41) 98829-7008', PhoneNumber::format('5541988297008'));
    }
}
