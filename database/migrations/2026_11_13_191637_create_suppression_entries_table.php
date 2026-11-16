<?php

use App\Enums\Growth\OutreachStepChannel;
use App\Enums\Growth\SuppressionReason;
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
        Schema::create('suppression_entries', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->string('channel', 20)->default(OutreachStepChannel::EMAIL->value);
            $table->char('identifier_hash', 64);
            $table->string('reason', 20)->default(SuppressionReason::MANUAL->value);
            $table->timestamp('effective_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_sup_entries_brand_id');
            $table->unique(['organisation_id', 'brand_id', 'channel', 'identifier_hash'], 'uq_sup_entries_identifier');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_suppression_entrie_brand_0f955b93')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppression_entries');
    }
};
