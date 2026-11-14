<?php

namespace Tests\Feature\Crm;

use App\Modules\Crm\Mail\DailyDigestMail;
use App\Modules\Crm\Mail\LeadEmail;
use App\Modules\Crm\Mail\NewLeadMail;
use App\Modules\Crm\Mail\TaskReminderMail;
use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Crm\Models\LeadTask;
use App\Modules\Lead\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithCrm;
use Tests\TestCase;

class TasksAndNotificationsTest extends TestCase
{
    use InteractsWithCrm;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->tenant = $this->createTenantWithRoles();
    }

    public function test_new_site_lead_alerts_by_email_and_telegram(): void
    {
        $admin = $this->createAdmin($this->tenant, ['email' => 'dono@nox.dev']);
        CrmSettings::query()->create(['tenant_id' => $this->tenant->id, 'telegram_bot_token' => '123:abc', 'telegram_chat_id' => '999']);

        $this->formLead(['name' => 'João']);

        Mail::assertSent(NewLeadMail::class, fn ($mail) => $mail->hasTo($admin->email));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'bot123:abc/sendMessage') && str_contains($request['text'], 'João'));
    }

    public function test_auto_reply_goes_to_form_lead_with_unsubscribe_link(): void
    {
        CrmSettings::query()->create([
            'tenant_id' => $this->tenant->id, 'auto_reply_enabled' => true,
            'auto_reply_subject' => 'Oi {{primeiro_nome}}', 'auto_reply_body' => 'Recebemos, {{primeiro_nome}}!',
        ]);

        $lead = $this->formLead(['name' => 'Ana Lima', 'email' => 'ana@example.com']);

        Mail::assertSent(LeadEmail::class, fn (LeadEmail $mail) => $mail->hasTo('ana@example.com')
            && $mail->subjectLine === 'Oi Ana' && $mail->body === 'Recebemos, Ana!' && str_contains($mail->unsubscribeUrl, $lead->uuid));
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'email_sent']);
    }

    public function test_task_lifecycle_and_history(): void
    {
        $lead = $this->siteLead();
        $admin = $this->actingAsAdmin();

        $id = $this->postJson("/api/leads/{$lead->uuid}/tasks", ['title' => 'Ligar para o cliente', 'due_at' => now()->addDay()->toIso8601String()])
            ->assertCreated()->assertJsonPath('data.assignee.id', $admin->uuid)->json('data.id');

        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/pipeline/board')->assertOk()->assertJsonPath('data.0.leads.0.open_tasks_count', 1);

        $this->patchJson("/api/tasks/{$id}", ['completed' => true])->assertOk()->assertJsonPath('data.overdue', false);
        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/tasks?status=done')->assertOk()->assertJsonCount(1, 'data');
        $types = collect($this->getJson("/api/leads/{$lead->uuid}/activities")->json('data'))->pluck('type');
        $this->assertContains('task_created', $types);
        $this->assertContains('task_completed', $types);
    }

    public function test_due_task_sends_single_reminder(): void
    {
        $lead = $this->siteLead();
        $admin = $this->actingAsAdmin();
        $this->postJson("/api/leads/{$lead->uuid}/tasks", ['title' => 'Enviar proposta', 'due_at' => now()->subMinute()->toIso8601String()])->assertCreated();
        $this->forgetTenantContext();

        $this->artisan('crm:task-reminders')->assertSuccessful();
        $this->artisan('crm:task-reminders')->assertSuccessful();

        Mail::assertSent(TaskReminderMail::class, 1);
        Mail::assertSent(TaskReminderMail::class, fn ($mail) => $mail->hasTo($admin->email));
        $this->assertNotNull(LeadTask::withoutTenancy()->first()->reminded_at);
    }

    public function test_stale_leads_and_daily_digest(): void
    {
        $lead = $this->siteLead();
        Lead::withoutTenancy()->whereKey($lead->id)->update(['last_activity_at' => now()->subDays(10), 'created_at' => now()->subDays(10)]);
        $this->actingAsAdmin();

        $this->getJson('/api/crm/stale-leads')->assertOk()->assertJsonPath('data.0.id', $lead->uuid);
        $this->forgetTenantContext();

        $this->artisan('crm:daily-digest')->assertSuccessful();
        Mail::assertSent(DailyDigestMail::class, fn ($mail) => $mail->digest['stale']->count() === 1);
    }

    public function test_settings_keep_secret_and_validate_pattern(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/crm/settings', ['post_url_pattern' => '/blog/'])->assertUnprocessable()->assertJsonValidationErrors('post_url_pattern');
        $this->putJson('/api/crm/settings', ['telegram_bot_token' => '123:abc', 'telegram_chat_id' => '42'])->assertOk()->assertJsonPath('data.has_telegram_bot_token', true);
        $this->putJson('/api/crm/settings', ['telegram_bot_token' => '', 'stale_lead_days' => 5])->assertOk()
            ->assertJsonPath('data.has_telegram_bot_token', true)->assertJsonPath('data.stale_lead_days', 5)
            ->assertJsonMissingPath('data.telegram_bot_token');

        $this->postJson('/api/crm/settings/test')->assertOk();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_tags_sync_and_filter(): void
    {
        $lead = $this->siteLead();
        $this->actingAsAdmin();

        $this->putJson("/api/leads/{$lead->uuid}/tags", ['tags' => ['ERP', 'Indústria', 'erp']])->assertOk()->assertJsonCount(2, 'data.tags');
        $tag = collect($this->getJson('/api/lead-tags')->assertOk()->json('data'))->firstWhere('name', 'ERP');
        $this->assertSame(1, $tag['leads_count']);
        $this->getJson("/api/leads?tag={$tag['id']}")->assertOk()->assertJsonPath('data.0.id', $lead->uuid);
    }
}
