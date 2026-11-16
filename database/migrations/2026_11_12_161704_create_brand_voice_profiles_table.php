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
        Schema::create('brand_voice_profiles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->string('name');
            $table->string('locale', 30)->default(Locale::EN_NG->value);
            $table->text('tone_description')->nullable();
            $table->unsignedSmallInteger('formality')->nullable();
            $table->unsignedSmallInteger('warmth')->nullable();
            $table->unsignedSmallInteger('directness')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'name', 'locale']);
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_voice_profil_brand_id_75442600');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_voice_profil_brand_a378228f')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_voice_profiles');
    }
};
