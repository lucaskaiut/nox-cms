<?php

namespace Tests\Feature\SearchConsole;

use App\Modules\Crm\Models\CrmSettings;
use App\Modules\SearchConsole\Models\PostSearchMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithCrm;
use Tests\TestCase;

class SearchConsoleSyncTest extends TestCase
{
    use InteractsWithCrm;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenantWithRoles();
    }

    private function serviceAccountJson(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        return json_encode(['type' => 'service_account', 'client_email' => 'cms@proj.iam.gserviceaccount.com', 'private_key' => $pem]);
    }

    public function test_connect_and_import_metrics_by_post(): void
    {
        $post = $this->createPost(['slug' => 'guia-de-seo']);
        CrmSettings::query()->create(['tenant_id' => $this->tenant->id, 'post_url_pattern' => '/blog/{slug}']);
        $day = now()->subDays(3)->toDateString();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.x']),
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [['siteUrl' => 'https://nox.dev/', 'permissionLevel' => 'siteFullUser']]]),
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [
                ['keys' => [$day, 'https://nox.dev/blog/guia-de-seo'], 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 4],
                ['keys' => [$day, 'https://nox.dev/blog/guia-de-seo?amp=1'], 'clicks' => 2, 'impressions' => 300, 'ctr' => 0.01, 'position' => 8],
                ['keys' => [$day, 'https://nox.dev/contato'], 'clicks' => 50, 'impressions' => 500, 'ctr' => 0.1, 'position' => 1],
            ]]),
        ]);
        $this->actingAsAdmin();

        $this->putJson('/api/integrations/search-console', ['site_url' => 'https://nox.dev/', 'service_account_json' => '{"type":"x"}'])
            ->assertUnprocessable()->assertJsonValidationErrors('service_account_json');
        $this->putJson('/api/integrations/search-console', ['site_url' => 'https://nox.dev/', 'service_account_json' => $this->serviceAccountJson()])
            ->assertOk()->assertJsonPath('data.service_account_email', 'cms@proj.iam.gserviceaccount.com');
        $this->postJson('/api/integrations/search-console/activate')->assertOk()->assertJsonPath('data.is_active', true);

        $metric = PostSearchMetric::withoutTenancy()->firstOrFail();
        $this->assertSame($post->id, $metric->post_id);
        $this->assertSame(12, $metric->clicks);
        $this->assertSame(400, $metric->impressions);
        $this->assertEqualsWithDelta(7.0, $metric->position, 0.01); // ponderada por impressões
        $this->assertSame(1, PostSearchMetric::withoutTenancy()->count());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2') && $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer' && substr_count($r['assertion'], '.') === 2);
    }

    public function test_activation_fails_without_property_access(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.x']),
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => []]),
        ]);
        $this->actingAsAdmin();
        $this->putJson('/api/integrations/search-console', ['site_url' => 'sc-domain:nox.dev', 'service_account_json' => $this->serviceAccountJson()])->assertOk();

        $this->postJson('/api/integrations/search-console/activate')->assertUnprocessable()->assertJsonValidationErrors('site_url');
    }
}
