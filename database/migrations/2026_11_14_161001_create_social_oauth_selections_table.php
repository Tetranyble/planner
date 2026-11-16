<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_oauth_selections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 24);
            $table->longText('grant_ciphertext');
            $table->longText('candidates_ciphertext');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->foreign(['organisation_id', 'brand_id'], 'fk_social_oauth_selections_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->index(['organisation_id', 'brand_id', 'user_id', 'expires_at'], 'idx_social_oauth_selections_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_oauth_selections');
    }
};
