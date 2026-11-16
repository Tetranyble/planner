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
        Schema::create('brand_claim_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->boolean('approved_claims_only')->default(true);
            $table->boolean('evidence_required_for_quantified')->default(true);
            $table->boolean('evidence_required_for_comparisons')->default(true);
            $table->boolean('allow_guaranteed_outcome_claims')->default(false);
            $table->boolean('require_review_for_new_claims')->default(true);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_claim_polici_brand_id_63a62326');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_claim_polici_brand_9e8c250c')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_claim_policies');
    }
};
