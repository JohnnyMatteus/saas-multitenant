<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->string('subject', 255);
            $table->string('email', 255)->nullable();
            $table->string('name', 120);
            $table->string('role', 32);
            $table->timestamps();

            // Mesmo "sub" pode existir em tenants diferentes (um IdP por estúdio no futuro).
            $table->unique(['tenant_id', 'subject']);
            // Alvo das FKs compostas: garante no banco que filhos e pai são do mesmo tenant.
            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
