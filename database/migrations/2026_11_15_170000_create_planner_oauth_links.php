<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_oauth_links', function (Blueprint $table): void {
            $table->id();
            // Passport client IDs can be UUIDs, strings or integers across supported versions.
            $table->string('oauth_client_id', 160);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->unsignedBigInteger('brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_oauth_link_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreignId('delegation_id')->constrained('planner_agent_delegations')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['oauth_client_id', 'user_id'], 'uq_oauth_link_client_user');
            $table->index(['organisation_id', 'brand_id', 'user_id'], 'idx_oauth_link_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_oauth_links');
    }
};
