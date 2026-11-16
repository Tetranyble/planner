<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_grant_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grant_group_id')->constrained('provider_grant_groups')->restrictOnDelete();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('provider_connection_id');
            $table->timestamp('detached_at')->nullable();
            $table->timestamps();
            $table->unique('provider_connection_id', 'uq_grant_link_connection');
            $table->index(['grant_group_id', 'detached_at'], 'idx_grant_link_group');
            $table->foreign(['organisation_id', 'provider_connection_id'], 'fk_grant_link_connection')
                ->references(['organisation_id', 'id'])->on('provider_connections')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_grant_links');
    }
};
