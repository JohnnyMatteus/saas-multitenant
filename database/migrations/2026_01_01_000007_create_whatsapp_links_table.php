<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->ulid('user_id');
            // HMAC-SHA256 com segredo de configuração: o telefone em claro nunca é gravado.
            $table->char('contact_hash', 64)->nullable();
            $table->char('code_hash', 64)->nullable();
            $table->dateTime('code_expires_at')->nullable()->comment('UTC');
            $table->dateTime('linked_at')->nullable()->comment('UTC');
            $table->timestamps();

            // Um telefone por aluno e um aluno por telefone, dentro do estúdio.
            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'contact_hash']);
            // Códigos pendentes não colidem no estúdio: o agente localiza o aluno só pelo código.
            $table->unique(['tenant_id', 'code_hash']);
            $table->foreign(['user_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_links');
    }
};
