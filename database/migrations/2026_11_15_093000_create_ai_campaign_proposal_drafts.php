<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_campaign_proposal_drafts', function (Blueprint $t): void {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('ai_campaign_proposal_id');
            $t->unsignedBigInteger('content_item_id');
            $t->unsignedBigInteger('created_by_user_id');
            $t->char('approved_proposal_hash', 64);
            $t->unique(['organisation_id', 'ai_campaign_proposal_id'], 'uq_ai_campaign_draft_proposal');
            $t->unique(['organisation_id', 'content_item_id'], 'uq_ai_campaign_draft_item');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_ai_campaign_draft_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $t->foreign(['organisation_id', 'brand_id', 'ai_campaign_proposal_id'], 'fk_ai_campaign_draft_proposal')
                ->references(['organisation_id', 'brand_id', 'id'])->on('ai_campaign_proposals')->restrictOnDelete();
            $t->foreign(['organisation_id', 'brand_id', 'content_item_id'], 'fk_ai_campaign_draft_content')
                ->references(['organisation_id', 'brand_id', 'id'])->on('content_items')->restrictOnDelete();
            $t->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_campaign_proposal_drafts');
    }
};
