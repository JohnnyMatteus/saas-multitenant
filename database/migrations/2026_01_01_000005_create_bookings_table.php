<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->ulid('session_id');
            $table->ulid('member_id');
            $table->string('status', 16);
            $table->dateTime('queued_at', 6)->comment('UTC; ordem da lista de espera');
            $table->dateTime('confirmed_at', 6)->nullable();
            $table->dateTime('canceled_at', 6)->nullable();
            $table->timestamps(6);

            // Uma linha por aluno e aula: barra dupla reserva na mesma aula mesmo se o lock falhar.
            $table->unique(['session_id', 'member_id']);
            $table->index(['session_id', 'status', 'queued_at']);
            $table->index(['member_id', 'status']);
            $table->foreign(['session_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('class_sessions');
            $table->foreign(['member_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('users');
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_valid CHECK (status IN ('confirmed', 'waitlisted', 'canceled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
