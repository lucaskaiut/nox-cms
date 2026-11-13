<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Cloud API (oficial da Meta), configurado manualmente por tenant
 * (sem Embedded Signup): credenciais, conversas e mensagens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('phone_number_id', 40)->nullable()->index();
            $table->string('waba_id', 40)->nullable();
            $table->string('display_phone', 40)->nullable();
            $table->string('verified_name', 120)->nullable();
            $table->text('access_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->string('verify_token', 80)->index();
            $table->string('graph_version', 10)->default('v23.0');
            $table->boolean('is_active')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('wa_id', 20);
            $table->string('contact_name', 120)->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('last_message_preview', 255)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'wa_id']);
            $table->index(['tenant_id', 'last_message_at']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('wamid', 191)->nullable();
            $table->string('direction', 3)->comment('in|out');
            $table->string('type', 20);
            $table->text('body')->nullable();
            $table->string('media_id', 80)->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->string('media_filename', 255)->nullable();
            $table->string('template_name', 120)->nullable();
            $table->string('status', 12)->default('pending');
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'wamid']);
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_accounts');
    }
};
