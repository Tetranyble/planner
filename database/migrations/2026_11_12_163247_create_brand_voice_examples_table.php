<?php

use App\Enums\VoiceExampleKind;
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
        Schema::create('brand_voice_examples', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('voice_profile_id');

            $table->string('example_kind', 10)->default(VoiceExampleKind::GOOD->value); // VoiceExampleKind
            $table->string('example_text');
            $table->text('explanation')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('voice_profile_id')->references('id')->on('brand_voice_profiles')
                ->onDelete('restrict');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_voice_exampl_brand_id_33d685d0');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_voice_exampl_brand_4a92fbfc')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'voice_profile_id'], 'fk_brand_voice_exampl_voice_profile_id_fd5079e5')->references(['organisation_id', 'brand_id', 'id'])->on('brand_voice_profiles')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_voice_examples');
    }
};
