<?php

use App\Enums\ClaimStatus;
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
        Schema::create('brand_claims', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->text('claim_text');
            $table->string('claim_category', 30);           // ClaimCategory
            $table->string('status', 20)->default(ClaimStatus::DRAFT->value); // ClaimStatus
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestampTz('approved_at')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('approved_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_claims_brand_id_db27b361');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_claims_brand_3120f7a6')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_claims');
    }
};
