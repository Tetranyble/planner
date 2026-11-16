<?php

use App\Enums\VoiceEnforcement;
use App\Enums\VoiceRuleKind;
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
        Schema::create('brand_voice_rules', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('voice_profile_id');

            $table->string('rule_kind', 20)->default(VoiceRuleKind::DO->value);       // VoiceRuleKind
            $table->text('instruction');
            $table->string('enforcement', 20)->default(VoiceEnforcement::GUIDELINE->value); // VoiceEnforcement
            $table->string('platform')->nullable();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->boolean('is_active')->default(true);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('voice_profile_id')->references('id')->on('brand_voice_profiles')
                ->onDelete('restrict');

            $table->index(
                ['organisation_id', 'brand_id', 'voice_profile_id', 'priority'],
                'idx_brand_voice_rules'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_voice_rules_brand_id_d45cc35b');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_voice_rules_brand_cc05f904')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'voice_profile_id'], 'fk_brand_voice_rules_voice_profile_id_f1058c5b')->references(['organisation_id', 'brand_id', 'id'])->on('brand_voice_profiles')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_voice_rules');
    }
};
