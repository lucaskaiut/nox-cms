<?php

namespace Tests\Feature\AiPublisher;

use App\Modules\AiPublisher\Models\AiContentJob;
use App\Modules\Post\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AiPublisherTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_discovery_returns_resources_and_endpoints(): void
    {
        Sanctum::actingAs($this->createAdmin($this->createTenantWithRoles()));
        $this->getJson('/api/ai/discovery')->assertOk()->assertJsonPath('data.resources.posts', true);
    }

    public function test_docs_returns_recommended_flow(): void
    {
        Sanctum::actingAs($this->createAdmin($this->createTenantWithRoles()));
        $this->getJson('/api/ai/docs')->assertOk()->assertJsonPath('data.recommended_flow.0', fn (string $v) => str_contains($v, 'discovery'));
    }

    public function test_schema_post_is_dynamic(): void
    {
        Sanctum::actingAs($this->createAdmin($this->createTenantWithRoles()));
        $this->getJson('/api/ai/schema/post')->assertOk()->assertJsonPath('data.resource', 'post');
    }

    public function test_editorial_guide_returns_configured_values(): void
    {
        Sanctum::actingAs($this->createAdmin($this->createTenantWithRoles()));
        $this->getJson('/api/ai/editorial-guide')->assertOk()->assertJsonPath('data.tone', 'profissional');
    }

    public function test_ai_publish_creates_post_as_published_by_default_and_audits_job(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));
        Category::factory()->for($tenant)->create(['name' => 'Notícias', 'slug' => 'noticias']);

        $this->postJson('/api/ai/posts', ['title' => 'Post IA', 'content' => '<p>'.str_repeat('x ', 200).'</p>', 'category' => 'Notícias', 'tags' => ['IA'], 'source' => 'hermes'])
            ->assertCreated()->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('ai_content_jobs', ['source' => 'hermes', 'status' => 'completed']);
    }

    public function test_ai_publish_auto_generates_missing_fields(): void
    {
        Sanctum::actingAs($this->createAdmin($this->createTenantWithRoles()));
        $r = $this->postJson('/api/ai/posts', ['title' => 'Título Automático', 'content' => '<p>'.str_repeat('Texto suficiente. ', 15).'</p>'])->assertCreated();
        $this->assertNotEmpty($r->json('data.excerpt')); $this->assertNotEmpty($r->json('data.slug')); $this->assertTrue($r->json('data.reading_time') > 0);
    }

    public function test_ai_publish_fails_on_short_content(): void
    {
        Sanctum::actingAs($this->createAdmin($this->createTenantWithRoles()));
        $this->postJson('/api/ai/posts', ['title' => 'Curto', 'content' => 'curto'])->assertStatus(500);
    }

    public function test_member_without_ai_permission_cannot_access(): void
    {
        Sanctum::actingAs($this->createMember($this->createTenantWithRoles()));
        $this->getJson('/api/ai/discovery')->assertForbidden();
    }
}
