<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_packages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->string('name', 120);
            $table->unsignedSmallInteger('lesson_count');
            $table->unsignedInteger('price_cents')->comment('Centavos; a moeda está em currency');
            $table->char('currency', 3)->default('BRL');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'active']);
        });

        Schema::create('package_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->ulid('package_id');
            $table->ulid('member_id');
            // Referência que o processador de pagamentos devolve nos eventos; única na instalação
            // inteira, porque o evento não traz o estúdio e é por ela que o tenant é descoberto.
            $table->string('payment_id', 64)->unique();
            // Cópia do pacote no momento da compra: mudar o catálogo não altera pedidos existentes.
            $table->unsignedSmallInteger('lesson_count');
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 16)->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['member_id', 'status']);
            $table->foreign(['package_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('lesson_packages');
            $table->foreign(['member_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('users');
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->ulid('order_id');
            // Idempotência: o mesmo evento entregue duas vezes (ou em paralelo) grava uma linha só.
            $table->string('event_id', 128)->unique();
            $table->string('transaction_id', 128);
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            $table->dateTime('occurred_at');
            $table->string('outcome', 24);
            $table->timestamps();

            $table->index('order_id');
            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('package_orders');
        });

        DB::statement('ALTER TABLE lesson_packages ADD CONSTRAINT lesson_packages_values_valid CHECK (lesson_count > 0 AND price_cents > 0)');
        DB::statement("ALTER TABLE package_orders ADD CONSTRAINT package_orders_status_valid CHECK (status IN ('pending', 'paid'))");
        DB::statement("ALTER TABLE payment_events ADD CONSTRAINT payment_events_direction_valid CHECK (direction IN ('credit', 'debit'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('package_orders');
        Schema::dropIfExists('lesson_packages');
    }
};
