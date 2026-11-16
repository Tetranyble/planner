<?php

use App\Enums\VoiceTermUsage;
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
        Schema::create('brand_voice_terms', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('voice_profile_id');

            $table->string('term');
            $table->string('usage', 20)->default(VoiceTermUsage::PREFER->value); // VoiceTermUsage
            $table->string('replacement_term')->nullable();
            $table->text('rationale')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('voice_profile_id')->references('id')->on('brand_voice_profiles')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'brand_id', 'voice_profile_id', 'term', 'usage'],
                'uq_brand_voice_terms'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_voice_terms_brand_id_97f65e43');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_voice_terms_brand_3705a64c')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'voice_profile_id'], 'fk_brand_voice_terms_voice_profile_id_7790a128')->references(['organisation_id', 'brand_id', 'id'])->on('brand_voice_profiles')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_voice_terms');
    }
};
