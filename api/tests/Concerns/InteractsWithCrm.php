<?php

namespace Tests\Concerns;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Lead\Models\Lead;
use App\Modules\Post\Models\Post;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/** Helpers para testes do CRM: lead vindo do site, admin logado, posts. */
trait InteractsWithCrm
{
    use InteractsWithTenants;

    protected Tenant $tenant;

    protected function siteLead(array $overrides = []): Lead
    {
        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($this->tenant)->create(['token_hash' => ApiToken::hash($plain), 'permissions' => [Permission::LEAD_CREATE->value]]);

        $ref = $overrides['ref'] ?? 'NX-'.strtoupper(Str::random(6));
        $ref = 'NX-'.preg_replace('/[^2-9A-HJ-NP-Z]/', 'K', substr($ref, 3, 6));
        $payload = array_merge([
            'ref' => $ref, 'channel' => 'whatsapp', 'gclid' => 'Cj0-abc', 'consent' => 'granted',
            'landing_path' => '/servicos',
        ], $overrides, ['ref' => $ref]);

        $this->postJson('/api/leads', $payload, ['Authorization' => "Bearer {$plain}"])->assertCreated();
        $this->forgetTenantContext();
        $this->app['auth']->forgetGuards();

        return Lead::withoutTenancy()->where('ref', $ref)->firstOrFail();
    }

    protected function formLead(array $overrides = []): Lead
    {
        return $this->siteLead(array_merge([
            'channel' => 'form', 'gclid' => null, 'name' => 'Maria Souza', 'email' => 'maria@example.com',
            'message' => 'Quero um orçamento',
        ], $overrides));
    }

    protected function actingAsAdmin(): User
    {
        $this->forgetTenantContext();
        $user = $this->createAdmin($this->tenant);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function createPost(array $attributes = []): Post
    {
        return Post::factory()->create(array_merge(['tenant_id' => $this->tenant->id, 'author_id' => User::factory()->for($this->tenant)], $attributes));
    }

    protected function stageId(string $name): string
    {
        return collect($this->getJson('/api/pipeline/stages')->assertOk()->json('data'))->firstWhere('name', $name)['id'];
    }
}
