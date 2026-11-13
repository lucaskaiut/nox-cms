<?php

namespace Tests\Feature\Crm;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Lead\Models\Lead;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenantWithRoles();
    }

    private function actingAsAdmin(): void
    {
        $this->forgetTenantContext();
        Sanctum::actingAs($this->createAdmin($this->tenant));
    }

    private function stageIds(): array
    {
        return collect($this->getJson('/api/pipeline/stages')->assertOk()->json('data'))->pluck('id', 'name')->all();
    }

    private function siteLead(string $ref = 'NX-ABC234', array $overrides = []): void
    {
        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($this->tenant)->create(['token_hash' => ApiToken::hash($plain), 'permissions' => [Permission::LEAD_CREATE->value]]);
        $this->postJson('/api/leads', array_merge([
            'ref' => $ref, 'channel' => 'whatsapp', 'gclid' => 'Cj0-abc', 'landing_path' => '/desenvolvimento-de-sistemas',
        ], $overrides), ['Authorization' => "Bearer {$plain}"])->assertCreated();
        $this->forgetTenantContext();
        $this->app['auth']->forgetGuards();
    }

    public function test_default_stages_are_created_on_first_access(): void
    {
        $this->actingAsAdmin();

        $stages = $this->getJson('/api/pipeline/stages')->assertOk()->json('data');

        $this->assertSame(['Novo', 'Em conversa', 'Qualificado', 'Proposta', 'Fechado', 'Perdido'], array_column($stages, 'name'));
        $this->assertSame('won', $stages[4]['outcome']);
    }

    public function test_site_lead_enters_first_stage_with_created_activity(): void
    {
        $this->siteLead();
        $this->actingAsAdmin();

        $board = $this->getJson('/api/pipeline/board')->assertOk()->json('data');
        $this->assertSame('NX-ABC234', $board[0]['leads'][0]['ref']);
        $this->assertSame(1, $board[0]['total']);

        $id = $board[0]['leads'][0]['id'];
        $this->getJson("/api/leads/{$id}/activities")->assertOk()->assertJsonPath('data.0.type', 'created');
    }

    public function test_moving_lead_updates_status_qualified_at_and_history(): void
    {
        $this->siteLead();
        $this->actingAsAdmin();
        $stages = $this->stageIds();
        $id = $this->getJson('/api/leads')->json('data.0.id');

        $this->postJson("/api/leads/{$id}/move", ['stage_id' => $stages['Proposta']])
            ->assertOk()
            ->assertJsonPath('data.status', 'qualified')
            ->assertJsonPath('data.stage.name', 'Proposta')
            ->assertJsonPath('data.qualified_at', fn ($v) => $v !== null);

        $this->getJson("/api/leads/{$id}/activities")->assertOk()
            ->assertJsonPath('data.0.type', 'stage_changed')
            ->assertJsonPath('data.0.meta.from', 'Novo')
            ->assertJsonPath('data.0.meta.to', 'Proposta');

        // Fechado entra na exportação para o Google Ads.
        $this->postJson("/api/leads/{$id}/move", ['stage_id' => $stages['Fechado']])->assertOk()->assertJsonPath('data.status', 'won');
        $this->assertStringContainsString('Cj0-abc', $this->get('/api/leads/export/google-ads')->getContent());
    }

    public function test_status_change_outside_board_moves_card(): void
    {
        $this->siteLead();
        $this->actingAsAdmin();
        $id = $this->getJson('/api/leads')->json('data.0.id');

        $this->patchJson("/api/leads/{$id}", ['status' => 'lost'])->assertOk()->assertJsonPath('data.stage.name', 'Perdido');
        $this->patchJson("/api/leads/{$id}", ['status' => 'spam'])->assertOk();

        $board = $this->getJson('/api/pipeline/board')->json('data');
        $this->assertSame(0, array_sum(array_column($board, 'total')), 'spam sai do quadro');
    }

    public function test_stage_crud_reorder_and_delete_with_move(): void
    {
        $this->siteLead();
        $this->actingAsAdmin();

        $new = $this->postJson('/api/pipeline/stages', ['name' => 'Reunião', 'color' => 'cyan', 'outcome' => 'qualified'])
            ->assertCreated()->assertJsonPath('data.position', 6)->json('data.id');
        $this->patchJson("/api/pipeline/stages/{$new}", ['name' => 'Reunião marcada'])->assertOk()->assertJsonPath('data.name', 'Reunião marcada');
        $this->postJson('/api/pipeline/stages', ['name' => 'X', 'color' => 'neon', 'outcome' => 'open'])->assertUnprocessable();

        $ids = array_values($this->stageIds());
        $reordered = [$ids[6], ...array_slice($ids, 0, 6)];
        $this->postJson('/api/pipeline/stages/reorder', ['ids' => $reordered])->assertOk()->assertJsonPath('data.0.name', 'Reunião marcada');
        $this->postJson('/api/pipeline/stages/reorder', ['ids' => array_slice($reordered, 0, 3)])->assertUnprocessable();

        $stages = $this->stageIds();
        $this->deleteJson("/api/pipeline/stages/{$stages['Novo']}")->assertUnprocessable()->assertJsonValidationErrors('move_to');
        $this->deleteJson("/api/pipeline/stages/{$stages['Novo']}", ['move_to' => $stages['Em conversa']])->assertOk();
        $this->assertSame('Em conversa', Lead::withoutTenancy()->first()->stage->name);
    }

    public function test_changing_stage_outcome_updates_its_leads(): void
    {
        $this->siteLead();
        $this->actingAsAdmin();
        $stages = $this->stageIds();

        $this->patchJson("/api/pipeline/stages/{$stages['Novo']}", ['outcome' => 'qualified'])->assertOk();

        $this->assertSame('qualified', Lead::withoutTenancy()->first()->status->value);
    }

    public function test_manual_lead_notes_and_search(): void
    {
        $this->actingAsAdmin();
        $stages = $this->stageIds();

        $lead = $this->postJson('/api/pipeline/leads', [
            'name' => 'João Indicação', 'phone' => '(41) 99999-1111', 'company' => 'ACME', 'stage_id' => $stages['Em conversa'],
        ])->assertCreated()->assertJsonPath('data.channel', 'manual')->assertJsonPath('data.stage.name', 'Em conversa');
        $this->assertMatchesRegularExpression('/^NX-[2-9A-HJ-NP-Z]{6}$/', $lead->json('data.ref'));
        $this->postJson('/api/pipeline/leads', ['name' => 'Sem contato'])->assertUnprocessable();

        $id = $lead->json('data.id');
        $this->postJson("/api/leads/{$id}/notes", ['body' => 'Ligar na segunda'])->assertCreated()->assertJsonPath('data.user.name', fn ($v) => $v !== null);
        $this->getJson("/api/leads/{$id}/activities")->assertJsonPath('data.0.body', 'Ligar na segunda');

        $board = $this->getJson('/api/pipeline/board?search=acme')->json('data');
        $this->assertSame(1, array_sum(array_column($board, 'total')));
    }

    public function test_member_without_permission_cannot_manage_stages(): void
    {
        $this->forgetTenantContext();
        Sanctum::actingAs($this->createMember($this->tenant));

        $this->postJson('/api/pipeline/stages', ['name' => 'X', 'color' => 'blue', 'outcome' => 'open'])->assertForbidden();
        $this->getJson('/api/pipeline/board')->assertForbidden();
    }

    public function test_stages_are_isolated_per_tenant(): void
    {
        $this->actingAsAdmin();
        $ids = $this->stageIds();

        $other = $this->createTenantWithRoles();
        $this->forgetTenantContext();
        Sanctum::actingAs($this->createAdmin($other));

        $this->patchJson("/api/pipeline/stages/{$ids['Novo']}", ['name' => 'Invadido'])->assertNotFound();
        $this->assertSame('Novo', PipelineStage::withoutTenancy()->where('uuid', $ids['Novo'])->value('name'));
    }
}
