<?php

namespace Tests\Feature\Analytics;

use App\Modules\Lead\Models\Lead;
use App\Modules\SearchConsole\Models\PostSearchMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCrm;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use InteractsWithCrm;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->tenant = $this->createTenantWithRoles();
    }

    public function test_funnel_report_totals_sources_spend_and_lost_reasons(): void
    {
        $post = $this->createPost(['slug' => 'guia-erp']);
        $paid = $this->siteLead();                                       // anúncio
        $blog = $this->formLead(['landing_path' => '/blog/guia-erp']);  // blog
        $this->actingAsAdmin();

        $this->patchJson("/api/leads/{$paid->uuid}", ['status' => 'won', 'conversion_value' => 5000])->assertOk();
        $this->postJson("/api/leads/{$blog->uuid}/move", ['stage_id' => $this->stageId('Perdido'), 'lost_reason' => 'Preço'])->assertOk();
        $this->postJson('/api/ad-spends', ['channel' => 'google_ads', 'period_start' => now('America/Sao_Paulo')->subDays(29)->toDateString(), 'period_end' => now('America/Sao_Paulo')->toDateString(), 'amount' => 1000])->assertCreated();

        $data = $this->getJson('/api/reports/funnel')->assertOk()->json('data');

        $this->assertSame(2, $data['totals']['leads']);
        $this->assertSame(1, $data['totals']['won']);
        $this->assertSame(1, $data['totals']['lost']);
        $this->assertEquals(5000, $data['totals']['revenue']);
        $this->assertEquals(50.0, $data['totals']['win_rate']);
        $this->assertEqualsWithDelta(1000, $data['spend']['total'], 0.01);
        $this->assertEqualsWithDelta(1000, $data['spend']['cost_per_lead'], 0.01);
        $this->assertEqualsWithDelta(5, $data['spend']['roas'], 0.01);
        $this->assertSame('Preço', $data['lost_reasons'][0]['reason']);
        $this->assertEqualsCanonicalizing(['paid', 'blog'], array_column($data['by_source'], 'source'));
        $this->assertCount(30, $data['series']);
        $this->assertSame($post->id, $blog->refresh()->post_id);
    }

    public function test_content_report_ranks_posts_by_leads_and_search(): void
    {
        $good = $this->createPost(['slug' => 'post-que-converte', 'title' => 'Post que converte']);
        $traffic = $this->createPost(['slug' => 'post-so-trafego', 'title' => 'Post só tráfego']);
        $this->formLead(['landing_path' => '/blog/post-que-converte']);
        PostSearchMetric::query()->create(['tenant_id' => $this->tenant->id, 'post_id' => $traffic->id, 'date' => now()->subDays(2)->toDateString(), 'clicks' => 120, 'impressions' => 3000, 'position' => 6.5]);
        $this->actingAsAdmin();

        $data = $this->getJson('/api/reports/content')->assertOk()->json('data');

        $this->assertSame($good->uuid, $data['posts'][0]['id']);
        $this->assertSame(1, $data['posts'][0]['leads']);
        $row = collect($data['posts'])->firstWhere('id', $traffic->uuid);
        $this->assertSame(120, $row['clicks']);
        $this->assertEquals(6.5, $row['position']);

        $stale = $this->getJson('/api/reports/stale-posts')->assertOk()->json('data');
        $this->assertSame('no_leads', collect($stale)->firstWhere('id', $traffic->uuid)['reasons'][0]['type']);
    }

    public function test_link_suggestions_use_shared_terms_and_detect_existing_links(): void
    {
        $base = $this->createPost(['title' => 'Como escolher um sistema ERP para indústria', 'slug' => 'escolher-erp', 'content' => '<p>Veja <a href="/blog/custo-erp">custos</a>.</p>']);
        $this->createPost(['title' => 'Quanto custa implantar um sistema ERP', 'slug' => 'custo-erp', 'content' => '<p>sem links</p>']);
        $this->createPost(['title' => 'Receita de bolo de cenoura', 'slug' => 'bolo']);
        $this->actingAsAdmin();

        $data = $this->getJson("/api/posts/{$base->uuid}/link-suggestions")->assertOk()->json('data');

        $this->assertSame('custo-erp', $data['link_to'][0]['slug']);
        $this->assertTrue($data['link_to'][0]['already_linked']);
        $this->assertSame('custo-erp', $data['link_from'][0]['slug']);
        $this->assertNotContains('bolo', array_column($data['link_to'], 'slug'));

        $this->getJson("/api/posts/{$base->uuid}/performance")->assertOk()->assertJsonPath('data.leads', 0);
    }

    public function test_reports_require_permission(): void
    {
        $this->forgetTenantContext();
        Sanctum::actingAs($this->createMember($this->tenant));

        $this->getJson('/api/reports/funnel')->assertForbidden();
        $this->postJson('/api/ad-spends', [])->assertForbidden();
    }

    public function test_ai_editorial_guide_includes_performance_insights(): void
    {
        $this->createPost(['slug' => 'post-top', 'title' => 'Post top']);
        $this->formLead(['landing_path' => '/blog/post-top', 'cta' => 'Orçamento']);
        $this->actingAsAdmin();

        $this->getJson('/api/ai/editorial-guide')->assertOk()
            ->assertJsonPath('data.performance_insights.top_posts.0.slug', 'post-top')
            ->assertJsonPath('data.performance_insights.best_offers.0.offer', 'Orçamento');
        $this->assertSame(1, Lead::withoutTenancy()->count());
    }
}
