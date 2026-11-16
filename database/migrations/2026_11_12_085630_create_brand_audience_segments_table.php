<?php

use App\Enums\AudienceKind;
use App\Enums\StatusEnum;
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
        Schema::create('brand_audience_segments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->string('name');
            $table->string('description')->nullable();
            $table->string('audience_kind', 30)->default(AudienceKind::BUSINESS->value); // AudienceKind
            $table->unsignedSmallInteger('priority')->default(50); // range 1-100
            $table->string('status')->default(StatusEnum::ACTIVE->value);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')
                ->references('id')->on('brands')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'brand_id', 'id'],
                'uq_org_brand_audience_segment_id');
            $table->unique(['organisation_id', 'brand_id', 'name'], 'uq_org_brand_audience_segment');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_audience_seg_brand_15abf78f')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_audience_segments');
    }
};
