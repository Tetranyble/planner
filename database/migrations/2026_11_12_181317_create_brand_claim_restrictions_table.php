<?php

use App\Enums\ClaimRestrictionKind;
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
        Schema::create('brand_claim_restrictions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->text('restricted_text');
            $table->string('restriction_kind', 30)->default(ClaimRestrictionKind::PROHIBITED_PHRASE->value); // ClaimRestrictionKind
            $table->text('explanation');
            $table->boolean('active')->default(true);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_claim_restri_brand_id_4e30d5c4');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_claim_restri_brand_cd691d83')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_claim_restrictions');
    }
};
