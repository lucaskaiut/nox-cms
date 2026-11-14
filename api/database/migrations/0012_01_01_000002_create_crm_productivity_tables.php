<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags e tarefas de follow-up dos leads, investimento em anúncios (para custo
 * por lead) e configurações comerciais do tenant (alertas, resposta automática,
 * motivos de perda, endereço do site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('slate');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('lead_tag', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_tag_id')->constrained('lead_tags')->cascadeOnDelete();
            $table->primary(['lead_id', 'lead_tag_id']);
        });

        Schema::create('lead_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'completed_at', 'due_at']);
        });

        Schema::create('ad_spends', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel', 30)->default('google_ads');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 12, 2);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'period_start']);
        });

        Schema::create('crm_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('site_url', 255)->nullable();
            $table->string('post_url_pattern', 255)->nullable()->comment('ex.: /blog/{slug}');
            $table->json('lost_reasons')->nullable();
            $table->unsignedSmallInteger('stale_lead_days')->default(3);
            $table->unsignedSmallInteger('stale_post_months')->default(6);
            $table->json('notify_emails')->nullable();
            $table->boolean('notify_new_lead')->default(true);
            $table->boolean('notify_daily_digest')->default(true);
            $table->text('telegram_bot_token')->nullable();
            $table->string('telegram_chat_id', 60)->nullable();
            $table->boolean('auto_reply_enabled')->default(false);
            $table->string('auto_reply_subject', 160)->nullable();
            $table->text('auto_reply_body')->nullable();
            $table->string('email_signature', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_settings');
        Schema::dropIfExists('ad_spends');
        Schema::dropIfExists('lead_tasks');
        Schema::dropIfExists('lead_tag');
        Schema::dropIfExists('lead_tags');
    }
};
