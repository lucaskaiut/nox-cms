<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liga o lead ao post de origem e à oferta (CTA) que o converteu, e guarda
 * quando/por que o negócio foi ganho ou perdido. Posts ganham a oferta própria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('cta_offer', 120)->nullable()->after('is_featured');
            $table->string('cta_text', 255)->nullable()->after('cta_offer');
            $table->string('cta_url', 2048)->nullable()->after('cta_text');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('post_id')->nullable()->after('landing_path')->constrained('posts')->nullOnDelete();
            $table->string('cta', 120)->nullable()->after('post_id');
            $table->string('lost_reason', 120)->nullable()->after('qualified_at');
            $table->timestamp('lost_at')->nullable()->after('lost_reason');
            $table->timestamp('won_at')->nullable()->after('lost_at');
            $table->timestamp('email_opt_out_at')->nullable()->after('consent');
        });

        // Leads já ganhos/perdidos: usa a última mudança de etapa como data.
        DB::table('leads')->where('status', 'won')->whereNull('won_at')
            ->update(['won_at' => DB::raw('COALESCE(stage_changed_at, updated_at)')]);
        DB::table('leads')->where('status', 'lost')->whereNull('lost_at')
            ->update(['lost_at' => DB::raw('COALESCE(stage_changed_at, updated_at)')]);

        // Atribuição retroativa: último segmento do landing_path = slug do post.
        DB::table('leads')->whereNotNull('landing_path')->whereNull('post_id')
            ->orderBy('id')->select(['id', 'tenant_id', 'landing_path'])
            ->chunkById(500, function ($leads) {
                foreach ($leads as $lead) {
                    $slug = basename(rtrim((string) $lead->landing_path, '/'));
                    if ($slug === '' || $slug === '/') continue;
                    $postId = DB::table('posts')->where('tenant_id', $lead->tenant_id)->where('slug', $slug)->value('id');
                    if ($postId) DB::table('leads')->where('id', $lead->id)->update(['post_id' => $postId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('post_id');
            $table->dropColumn(['cta', 'lost_reason', 'lost_at', 'won_at', 'email_opt_out_at']);
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['cta_offer', 'cta_text', 'cta_url']);
        });
    }
};
