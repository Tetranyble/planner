<?php

use App\Enums\Content\ContentSeriesStatus;
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
        Schema::create('content_series', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->string('name');
            $table->text('summary')->nullable();
            $table->string('status', 20)->default(ContentSeriesStatus::ACTIVE->value);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_content_series_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_content_series_brand_f71aa207')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_series');
    }
};
