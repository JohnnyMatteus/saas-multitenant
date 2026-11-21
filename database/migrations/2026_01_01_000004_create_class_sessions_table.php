<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->ulid('series_id')->nullable();
            $table->ulid('instructor_id');
            $table->string('title', 120);
            $table->dateTime('starts_at')->comment('UTC');
            $table->dateTime('ends_at')->comment('UTC');
            $table->unsignedSmallInteger('capacity');
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'starts_at']);
            $table->foreign(['series_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('class_series');
            $table->foreign(['instructor_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('users');
        });

        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_capacity_positive CHECK (capacity > 0)');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_ends_after_start CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
