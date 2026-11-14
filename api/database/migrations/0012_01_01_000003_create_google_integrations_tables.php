<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrações com o Google: envio automático de conversões offline (Google Ads
 * API) e métricas de busca orgânica por post (Search Console).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('customer_id', 20)->nullable();
            $table->string('login_customer_id', 20)->nullable();
            $table->text('developer_token')->nullable();
            $table->string('client_id', 255)->nullable();
            $table->text('client_secret')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('qualified_action_id', 30)->nullable();
            $table->string('won_action_id', 30)->nullable();
            $table->string('currency', 3)->default('BRL');
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_upload_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_conversion_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('kind', 20)->comment('qualified|won');
            $table->string('status', 20)->default('pending')->comment('pending|uploaded|failed');
            $table->timestamp('conversion_at');
            $table->decimal('value', 12, 2)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->unique(['lead_id', 'kind']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('search_console_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('site_url', 255)->nullable()->comment('propriedade: https://site.com/ ou sc-domain:site.com');
            $table->text('service_account_json')->nullable();
            $table->string('service_account_email', 255)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('post_search_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 6, 2)->nullable();
            $table->unique(['post_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_search_metrics');
        Schema::dropIfExists('search_console_accounts');
        Schema::dropIfExists('lead_conversion_uploads');
        Schema::dropIfExists('google_ads_accounts');
    }
};
