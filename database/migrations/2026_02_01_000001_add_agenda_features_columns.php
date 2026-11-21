<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_series', function (Blueprint $table): void {
            $table->boolean('rolling')->default(false)->after('ends_on')
                ->comment('Série contínua: o job de horizonte estende ends_on');
        });

        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->dateTime('canceled_at')->nullable()->after('capacity')->comment('UTC; cancelada pelo estúdio');
            $table->string('cancel_reason', 255)->nullable()->after('canceled_at');
            // Torna a geração do horizonte idempotente mesmo com dois jobs concorrentes.
            $table->unique(['series_id', 'starts_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dateTime('reminder_sent_at', 6)->nullable()->after('canceled_at');
            $table->index(['status', 'reminder_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['status', 'reminder_sent_at']);
            $table->dropColumn('reminder_sent_at');
        });

        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->dropUnique(['series_id', 'starts_at']);
            $table->dropColumn(['canceled_at', 'cancel_reason']);
        });

        Schema::table('class_series', function (Blueprint $table): void {
            $table->dropColumn('rolling');
        });
    }
};
