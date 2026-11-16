<?php

use App\Enums\Growth\OutreachSequencePurpose;
use App\Enums\Growth\OutreachSequenceStatus;
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
        Schema::create('outreach_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->string('name');
            $table->string('purpose', 20)->default(OutreachSequencePurpose::DEMO_FOLLOWUP->value);
            $table->string('status', 20)->default(OutreachSequenceStatus::DRAFT->value);
            $table->unsignedInteger('max_messages')->default(3);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign(['organisation_id', 'brand_id'])
                ->references(['organisation_id', 'id'])->on('brands')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_outreach_sequences_tenant_brand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outreach_sequences');
    }
};
