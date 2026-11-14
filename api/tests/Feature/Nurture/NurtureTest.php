<?php

namespace Tests\Feature\Nurture;

use App\Modules\Crm\Mail\LeadEmail;
use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Lead\Models\Lead;
use App\Modules\Nurture\Models\NurtureEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithCrm;
use Tests\TestCase;

class NurtureTest extends TestCase
{
    use InteractsWithCrm;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->tenant = $this->createTenantWithRoles();
    }

    private function sequence(array $overrides = []): array
    {
        $post = $this->createPost(['slug' => 'case-erp', 'title' => 'Case ERP']);
        CrmSettings::query()->firstOrCreate(['tenant_id' => $this->tenant->id], ['site_url' => 'https://nox.dev', 'post_url_pattern' => '/blog/{slug}']);

        return $this->postJson('/api/nurture/sequences', array_merge([
            'name' => 'Boas-vindas', 'trigger' => 'manual',
            'steps' => [
                ['delay_days' => 0, 'subject' => 'Olá {{primeiro_nome}}', 'body' => 'Primeiro e-mail', 'post_id' => $post->uuid],
                ['delay_days' => 3, 'subject' => 'Segundo', 'body' => 'Segundo e-mail'],
            ],
        ], $overrides))->assertCreated()->json('data');
    }

    public function test_manual_enrollment_sends_steps_on_schedule(): void
    {
        $lead = $this->formLead(['name' => 'Carla Dias', 'email' => 'carla@example.com']);
        $this->actingAsAdmin();
        $seq = $this->sequence();

        $this->postJson("/api/leads/{$lead->uuid}/enrollments", ['sequence_id' => $seq['id']])->assertCreated()->assertJsonPath('data.status', 'active');
        $this->forgetTenantContext();

        $this->artisan('nurture:process')->assertSuccessful();
        Mail::assertSent(LeadEmail::class, fn (LeadEmail $m) => $m->subjectLine === 'Olá Carla'
            && str_contains($m->post['url'], 'https://nox.dev/blog/case-erp?utm_source=email'));

        $this->artisan('nurture:process')->assertSuccessful();
        Mail::assertSent(LeadEmail::class, 1); // segundo passo só daqui a 3 dias

        $this->travel(3)->days();
        $this->artisan('nurture:process')->assertSuccessful();
        Mail::assertSent(LeadEmail::class, 2);
        $this->assertSame('completed', NurtureEnrollment::withoutTenancy()->first()->status);
    }

    public function test_tag_trigger_enrolls_and_winning_stops(): void
    {
        $lead = $this->formLead();
        $this->actingAsAdmin();
        $this->putJson("/api/leads/{$lead->uuid}/tags", ['tags' => ['ERP']])->assertOk();
        $tag = $this->getJson('/api/lead-tags')->json('data.0.id');
        $seq = $this->sequence(['trigger' => 'tag', 'trigger_tag_id' => $tag, 'steps' => [['delay_days' => 2, 'subject' => 'A', 'body' => 'B']]]);

        $other = $this->formLead(['email' => 'outro@example.com']);
        $this->actingAsAdmin();
        $this->putJson("/api/leads/{$other->uuid}/tags", ['tags' => ['ERP']])->assertOk();
        $this->assertSame(1, NurtureEnrollment::withoutTenancy()->where('lead_id', $other->id)->where('status', 'active')->count());

        $this->patchJson("/api/leads/{$other->uuid}", ['status' => 'won'])->assertOk();
        $enrollment = NurtureEnrollment::withoutTenancy()->where('lead_id', $other->id)->first();
        $this->assertSame('stopped', $enrollment->status);
        $this->assertSame('Lead fechado', $enrollment->stopped_reason);
        $this->assertNotNull($seq['id']);
    }

    public function test_lost_leads_are_enrolled_for_reactivation(): void
    {
        $lead = $this->formLead();
        $this->actingAsAdmin();
        $this->sequence(['name' => 'Reativação', 'trigger' => 'lost', 'trigger_delay_days' => 60]);
        $this->patchJson("/api/leads/{$lead->uuid}", ['status' => 'lost', 'lost_reason' => 'Sem orçamento agora'])->assertOk();
        $this->forgetTenantContext();

        $this->artisan('nurture:process --enroll-lost')->assertSuccessful();
        $this->assertSame(0, NurtureEnrollment::withoutTenancy()->count());

        $this->travel(61)->days();
        $this->artisan('nurture:process --enroll-lost')->assertSuccessful();
        $this->assertSame(1, NurtureEnrollment::withoutTenancy()->count());
        Mail::assertSent(LeadEmail::class);
    }

    public function test_unsubscribe_link_opts_out_and_stops_sequences(): void
    {
        $lead = $this->formLead();
        $this->actingAsAdmin();
        $seq = $this->sequence(['steps' => [['delay_days' => 5, 'subject' => 'A', 'body' => 'B']]]);
        $this->postJson("/api/leads/{$lead->uuid}/enrollments", ['sequence_id' => $seq['id']])->assertCreated();
        $this->forgetTenantContext();
        $this->app['auth']->forgetGuards();

        $url = URL::signedRoute('leads.unsubscribe', ['lead' => $lead->uuid]);
        $this->get($url)->assertOk()->assertSee('Confirmar descadastro');
        $this->post($url)->assertOk()->assertSee('descadastrado');
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();

        $this->assertNotNull(Lead::withoutTenancy()->find($lead->id)->email_opt_out_at);
        $this->assertSame('stopped', NurtureEnrollment::withoutTenancy()->first()->status);

        $this->actingAsAdmin();
        $this->postJson("/api/leads/{$lead->uuid}/enrollments", ['sequence_id' => $seq['id']])->assertUnprocessable();
    }
}
