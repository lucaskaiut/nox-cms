<?php

namespace Tests\Feature\Lead;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Lead\Models\Lead;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private function tokenHeaders(Tenant $tenant, ?array $permissions = [Permission::LEAD_CREATE->value]): array
    {
        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenant)->create(['token_hash' => ApiToken::hash($plain), 'permissions' => $permissions]);

        return ['Authorization' => "Bearer {$plain}"];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'ref' => 'NX-ABC234', 'channel' => 'whatsapp', 'consent' => 'granted',
            'gclid' => 'Cj0KCQjw-abc_123', 'utm_source' => 'google',
            'landing_path' => '/desenvolvimento-de-sistemas', 'click_path' => '/desenvolvimento-de-sistemas',
            'captured_at' => now()->subMinutes(3)->toIso8601String(),
        ], $overrides);
    }

    public function test_site_token_registers_lead(): void
    {
        $tenant = $this->createTenantWithRoles();

        $this->postJson('/api/leads', $this->payload(), $this->tokenHeaders($tenant))
            ->assertCreated()
            ->assertJsonPath('data.ref', 'NX-ABC234')
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.gclid', 'Cj0KCQjw-abc_123');

        $this->assertDatabaseHas('leads', ['tenant_id' => $tenant->id, 'ref' => 'NX-ABC234', 'consent' => 'granted']);
    }

    public function test_repeated_ref_updates_same_lead_and_keeps_first_attribution(): void
    {
        $tenant = $this->createTenantWithRoles();
        $headers = $this->tokenHeaders($tenant);

        $this->postJson('/api/leads', $this->payload(), $headers)->assertCreated();
        $this->postJson('/api/leads', $this->payload(['gclid' => 'outro', 'click_path' => '/cases', 'consent' => null]), $headers)->assertCreated();

        $this->assertSame(1, Lead::withoutTenancy()->count());
        $lead = Lead::withoutTenancy()->first();
        $this->assertSame('Cj0KCQjw-abc_123', $lead->gclid);
        $this->assertSame('/cases', $lead->click_path);
        $this->assertSame('granted', $lead->consent);
    }

    public function test_store_requires_lead_create_scope(): void
    {
        $tenant = $this->createTenantWithRoles();

        $this->postJson('/api/leads', $this->payload(), $this->tokenHeaders($tenant, [Permission::POST_READ->value]))
            ->assertForbidden();
    }

    public function test_store_validates_ref_and_click_id(): void
    {
        $headers = $this->tokenHeaders($this->createTenantWithRoles());

        $this->postJson('/api/leads', $this->payload(['ref' => 'NX-0OI1AA']), $headers)->assertUnprocessable()->assertJsonValidationErrors('ref');
        $this->postJson('/api/leads', $this->payload(['gclid' => null]), $headers)->assertUnprocessable()->assertJsonValidationErrors('gclid');
        $this->postJson('/api/leads', $this->payload(['gclid' => null, 'wbraid' => 'abc']), $headers)->assertCreated();
        $this->postJson('/api/leads', $this->payload(['ref' => 'NX-ABC235', 'landing_path' => 'https://evil.example']), $headers)->assertUnprocessable();
    }

    public function test_admin_lists_and_qualifies_lead(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->postJson('/api/leads', $this->payload(), $this->tokenHeaders($tenant))->assertCreated();
        $this->forgetTenantContext();
        Sanctum::actingAs($this->createAdmin($tenant));

        $id = $this->getJson('/api/leads?ref=nx-abc234')->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');

        $this->patchJson("/api/leads/{$id}", ['status' => 'qualified', 'notes' => 'Quer ERP para 30 usuários'])
            ->assertOk()
            ->assertJsonPath('data.status', 'qualified')
            ->assertJsonPath('data.qualified_at', fn ($v) => $v !== null);

        $this->getJson('/api/leads?status=qualified')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_export_google_ads_csv_contains_only_qualified_leads_with_gclid(): void
    {
        $tenant = $this->createTenantWithRoles();
        $headers = $this->tokenHeaders($tenant);
        $this->postJson('/api/leads', $this->payload(), $headers)->assertCreated();
        $this->postJson('/api/leads', $this->payload(['ref' => 'NX-ABC235', 'gclid' => 'naoqualificado']), $headers)->assertCreated();
        $this->postJson('/api/leads', $this->payload(['ref' => 'NX-ABC236', 'gclid' => null, 'gbraid' => 'ios']), $headers)->assertCreated();

        $this->forgetTenantContext();
        Sanctum::actingAs($this->createAdmin($tenant));
        foreach (['NX-ABC234', 'NX-ABC236'] as $ref) {
            $id = $this->getJson("/api/leads?ref={$ref}")->json('data.0.id');
            $this->patchJson("/api/leads/{$id}", ['status' => 'won', 'conversion_value' => 15000])->assertOk();
        }

        $csv = $this->get('/api/leads/export/google-ads')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $lines = array_values(array_filter(explode("\n", $csv)));

        $this->assertSame('Parameters:TimeZone=America/Sao_Paulo', $lines[0]);
        $this->assertSame('"Google Click ID","Conversion Name","Conversion Time","Conversion Value","Conversion Currency","Ad User Data"', $lines[1]);
        $this->assertCount(3, $lines);
        $this->assertStringStartsWith('Cj0KCQjw-abc_123,"CRM | Lead qualificado (offline)",', $lines[2]);
        $this->assertStringEndsWith(',15000.00,BRL,GRANTED', $lines[2]);
    }

    public function test_tenant_isolation_and_member_permissions(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles();
        $this->postJson('/api/leads', $this->payload(), $this->tokenHeaders($tenantA))->assertCreated();
        $id = Lead::withoutTenancy()->value('uuid');

        $this->forgetTenantContext();
        Sanctum::actingAs($this->createAdmin($tenantB));
        $this->getJson("/api/leads/{$id}")->assertNotFound();
        $this->getJson('/api/leads')->assertOk()->assertJsonCount(0, 'data');

        $this->forgetTenantContext();
        Sanctum::actingAs($this->createMember($tenantA));
        $this->getJson('/api/leads')->assertForbidden();
    }
}
