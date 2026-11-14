<?php

namespace Tests\Feature\Analytics;

use App\Modules\Crm\Models\CrmSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithCrm;
use Tests\TestCase;

class AttributionAndOutcomeTest extends TestCase
{
    use InteractsWithCrm;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->tenant = $this->createTenantWithRoles();
    }

    public function test_lead_is_attributed_to_post_by_landing_path_and_keeps_cta(): void
    {
        $post = $this->createPost(['slug' => 'quanto-custa-um-sistema']);

        $lead = $this->formLead(['landing_path' => '/blog/quanto-custa-um-sistema', 'cta' => 'Diagnóstico grátis']);

        $this->assertSame($post->id, $lead->post_id);
        $this->assertSame('Diagnóstico grátis', $lead->cta);
        $this->assertSame('blog', $lead->source());
    }

    public function test_post_slug_wins_and_url_pattern_is_respected(): void
    {
        $a = $this->createPost(['slug' => 'post-a']);
        $this->createPost(['slug' => 'servicos']);
        CrmSettings::query()->create(['tenant_id' => $this->tenant->id, 'post_url_pattern' => '/blog/{slug}']);

        // Com padrão configurado, "/servicos" (página institucional) não é post.
        $this->assertNull($this->siteLead(['landing_path' => '/servicos'])->post_id);
        $this->assertSame($a->id, $this->siteLead(['landing_path' => '/servicos', 'post_slug' => 'post-a'])->post_id);
    }

    public function test_changing_url_pattern_backfills_attribution(): void
    {
        $post = $this->createPost(['slug' => 'meu-post']);
        CrmSettings::query()->create(['tenant_id' => $this->tenant->id, 'post_url_pattern' => '/artigos/{slug}']);
        $lead = $this->siteLead(['landing_path' => '/blog/meu-post']);
        $this->assertNull($lead->post_id);

        $this->actingAsAdmin();
        $this->putJson('/api/crm/settings', ['post_url_pattern' => '/blog/{slug}', 'site_url' => 'https://nox.dev'])->assertOk();

        $this->assertSame($post->id, $lead->refresh()->post_id);
    }

    public function test_lost_reason_and_outcome_dates_follow_stage_moves(): void
    {
        $lead = $this->siteLead();
        $this->actingAsAdmin();

        $this->postJson("/api/leads/{$lead->uuid}/move", ['stage_id' => $this->stageId('Perdido'), 'lost_reason' => 'Preço'])
            ->assertOk()->assertJsonPath('data.status', 'lost')->assertJsonPath('data.lost_reason', 'Preço');
        $this->assertNotNull($lead->refresh()->lost_at);

        $this->postJson("/api/leads/{$lead->uuid}/move", ['stage_id' => $this->stageId('Fechado')])
            ->assertOk()->assertJsonPath('data.status', 'won')->assertJsonPath('data.lost_reason', null);
        $lead->refresh();
        $this->assertNull($lead->lost_at);
        $this->assertNotNull($lead->won_at);
        $this->assertNotNull($lead->qualified_at);
    }

    public function test_status_update_with_lost_reason_moves_card(): void
    {
        $lead = $this->siteLead();
        $this->actingAsAdmin();

        $this->patchJson("/api/leads/{$lead->uuid}", ['status' => 'lost', 'lost_reason' => 'Sem resposta'])
            ->assertOk()->assertJsonPath('data.stage.name', 'Perdido')->assertJsonPath('data.lost_reason', 'Sem resposta');

        $this->getJson('/api/crm/lost-reasons')->assertOk()->assertJsonFragment(['Preço']);
    }
}
