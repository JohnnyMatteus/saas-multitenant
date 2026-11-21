<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_series', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->ulid('instructor_id');
            $table->string('title', 120);
            $table->json('weekdays');
            $table->char('start_time', 5);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('capacity');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->foreign(['instructor_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_series');
    }
};
