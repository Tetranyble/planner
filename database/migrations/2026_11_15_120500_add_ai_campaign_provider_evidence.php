<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_campaign_proposals', function (Blueprint $t): void {
            $t->char('provider_evidence_hash', 64)->nullable();
            $t->json('provider_evidence_snapshot')->nullable();
            $t->index(['organisation_id', 'brand_id', 'provider_evidence_hash'], 'idx_ai_proposal_provider_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('ai_campaign_proposals', function (Blueprint $t): void {
            $t->dropIndex('idx_ai_proposal_provider_evidence');
            $t->dropColumn(['provider_evidence_hash', 'provider_evidence_snapshot']);
        });
    }
};
