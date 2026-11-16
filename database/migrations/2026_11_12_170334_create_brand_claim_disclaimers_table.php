<?php

use App\Enums\Locale;
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
        Schema::create('brand_claim_disclaimers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('claim_id');

            $table->string('locale', 30)->default(Locale::EN_NG->value);
            $table->text('disclaimer_text');
            $table->boolean('required')->default(true);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('claim_id')->references('id')->on('brand_claims')
                ->onDelete('restrict');
            $table->unique(
                ['organisation_id', 'brand_id', 'claim_id', 'locale'],
                'uq_brand_claim_disclaimers'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_claim_discla_brand_id_661537e4');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_claim_discla_brand_5ab77dbe')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'claim_id'], 'fk_brand_claim_discla_claim_id_52764373')->references(['organisation_id', 'brand_id', 'id'])->on('brand_claims')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_claim_disclaimers');
    }
};
