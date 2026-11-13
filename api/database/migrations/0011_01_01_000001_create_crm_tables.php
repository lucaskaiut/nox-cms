<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CRM: etapas configuráveis do funil (kanban) e histórico de cada lead.
 * Cada tenant existente recebe as etapas padrão e os leads atuais são
 * distribuídos conforme o status.
 */
return new class extends Migration
{
    private const DEFAULT_STAGES = [
        ['Novo', 'slate', 'open'],
        ['Em conversa', 'blue', 'open'],
        ['Qualificado', 'amber', 'qualified'],
        ['Proposta', 'violet', 'qualified'],
        ['Fechado', 'green', 'won'],
        ['Perdido', 'red', 'lost'],
    ];

    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('slate');
            $table->string('outcome', 20)->default('open')->comment('open|qualified|won|lost');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'position']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('stage_id')->nullable()->after('status')->constrained('pipeline_stages')->nullOnDelete();
            $table->timestamp('stage_changed_at')->nullable()->after('stage_id');
            $table->timestamp('last_activity_at')->nullable()->after('stage_changed_at');
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
        });

        $now = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $ids = [];
            foreach (self::DEFAULT_STAGES as $position => [$name, $color, $outcome]) {
                $id = DB::table('pipeline_stages')->insertGetId([
                    'uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'name' => $name,
                    'color' => $color, 'outcome' => $outcome, 'position' => $position,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $ids[$outcome] ??= $id; // primeira etapa de cada resultado
            }

            foreach (['new' => 'open', 'qualified' => 'qualified', 'won' => 'won', 'lost' => 'lost'] as $status => $outcome) {
                DB::table('leads')->where('tenant_id', $tenantId)->where('status', $status)->update([
                    'stage_id' => $ids[$outcome], 'stage_changed_at' => $now,
                ]);
            }
            DB::table('leads')->where('tenant_id', $tenantId)->whereNull('last_activity_at')
                ->update(['last_activity_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_id');
            $table->dropColumn(['stage_changed_at', 'last_activity_at']);
        });
        Schema::dropIfExists('pipeline_stages');
    }
};
