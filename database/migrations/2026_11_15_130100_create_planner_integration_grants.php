<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_integration_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->unsignedBigInteger('brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_planner_integration_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('sanctum_token_id')->unique();
            $table->string('name', 100);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'brand_id', 'user_id', 'revoked_at'], 'idx_planner_integration_scope');
            // Keep the audit grant row after Sanctum token deletion/revocation.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_integration_grants');
    }
};
