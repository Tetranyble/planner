<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_ai_usage_reservations', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $t->date('usage_day');
            $t->string('purpose', 30);
            $t->string('status', 16)->default('RESERVED');
            $t->timestamp('expires_at');
            $t->timestamp('completed_at')->nullable();
            $t->string('model', 100)->nullable();
            $t->char('output_hash', 64)->nullable();
            $t->timestamps();
            $t->index(['organisation_id', 'brand_id', 'usage_day', 'status'], 'idx_ai_budget_day');
            $t->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_ai_usage_scope');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_brand_ai_usage_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_ai_usage_reservations');
    }
};
