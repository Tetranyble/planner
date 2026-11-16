<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('brand_claim_evidence', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('claim_id');
            $table->unsignedBigInteger('knowledge_source_id');

            $table->text('supporting_excerpt')->nullable();
            $table->text('source_locator')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreign('reviewed_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');
            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('claim_id')->references('id')->on('brand_claims')
                ->onDelete('restrict');
            $table->foreign('knowledge_source_id')->references('id')->on('brand_knowledge_sources')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'brand_id', 'claim_id', 'knowledge_source_id'],
                'uq_brand_claim_evidence'
            );

            $table->index(
                ['organisation_id', 'brand_id', 'claim_id'],
                'idx_brand_claim_evidence'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_claim_eviden_brand_id_1a85bd42');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_claim_eviden_brand_ed3c6475')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'claim_id'], 'fk_brand_claim_eviden_claim_id_2ee6c9d5')->references(['organisation_id', 'brand_id', 'id'])->on('brand_claims')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'knowledge_source_id'], 'fk_brand_claim_eviden_knowledge_source__550006e7')->references(['organisation_id', 'brand_id', 'id'])->on('brand_knowledge_sources')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_claim_evidence');
    }
};
