<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_content_suggestions', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $t->string('campaign_name', 180)->nullable();
            $t->string('topic', 500);
            $t->string('platform', 20);
            $t->string('status', 24)->default('SUGGESTED');
            $t->string('model', 100);
            $t->string('prompt_hash', 64);
            $t->string('result_hash', 64);
            $t->text('suggestion_text');
            $t->foreignId('promoted_content_item_id')->nullable()->constrained('content_items')->restrictOnDelete();
            $t->foreignId('promoted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('promoted_at')->nullable();
            $t->timestamps();
            $t->index(['organisation_id', 'brand_id', 'created_at'], 'idx_ai_suggestions_scope');
            $t->unique(['organisation_id', 'brand_id', 'id'], 'uq_ai_suggestion_scope');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_ai_suggestion_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_content_suggestions');
    }
};
