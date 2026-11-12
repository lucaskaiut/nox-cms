<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads do formulário do site trazem os dados de contato junto (o WhatsApp não:
 * a conversa acontece fora do site e o vínculo é só o código).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->after('channel');
            $table->string('email', 160)->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('company', 120)->nullable()->after('phone');
            $table->text('message')->nullable()->after('company');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['name', 'email', 'phone', 'company', 'message']);
        });
    }
};
