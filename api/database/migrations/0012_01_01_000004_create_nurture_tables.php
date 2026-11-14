<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nutrição por e-mail: sequências de passos (com atraso em dias), disparadas à
 * mão, ao receber uma tag ou N dias depois de o lead ser perdido (reativação).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nurture_sequences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('trigger', 20)->default('manual')->comment('manual|tag|lost');
            $table->foreignId('trigger_tag_id')->nullable()->constrained('lead_tags')->nullOnDelete();
            $table->unsignedSmallInteger('trigger_delay_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('nurture_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sequence_id')->constrained('nurture_sequences')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('delay_days')->default(0);
            $table->string('subject', 160);
            $table->text('body');
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamps();
            $table->index(['sequence_id', 'position']);
        });

        Schema::create('nurture_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('nurture_sequences')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->unsignedSmallInteger('next_step')->default(0);
            $table->timestamp('next_run_at')->nullable();
            $table->string('status', 20)->default('active')->comment('active|completed|stopped');
            $table->string('stopped_reason', 120)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['sequence_id', 'lead_id']);
            $table->index(['status', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nurture_enrollments');
        Schema::dropIfExists('nurture_steps');
        Schema::dropIfExists('nurture_sequences');
    }
};
