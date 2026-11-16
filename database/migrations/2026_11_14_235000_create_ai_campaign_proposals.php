<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_campaign_proposals', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $t->string('title', 180);
            $t->string('objective', 180);
            $t->string('audience', 200);
            $t->json('platforms');
            $t->unsignedTinyInteger('duration_days');
            $t->longText('proposal_text');
            $t->string('model', 100);
            $t->char('prompt_hash', 64);
            $t->char('result_hash', 64);
            $t->string('status', 24)->default('PROPOSED');
            $t->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note', 500)->nullable();
            $t->timestamps();
            $t->index(['organisation_id', 'brand_id', 'created_at'], 'idx_campaign_proposals_scope');
            $t->unique(['organisation_id', 'brand_id', 'id'], 'uq_campaign_proposals_scope');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_campaign_proposals_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_campaign_proposals');
    }
};
