<?php

use App\Enums\TrustStatus;
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
        Schema::create('brand_knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->string('title');
            $table->string('source_type');
            $table->string('source_url')->nullable();
            $table->string('object_key')->nullable();
            $table->text('content_text')->nullable();
            $table->string('content_hash');
            $table->string('trust_status', 30)->default(TrustStatus::PENDING->value);
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by_user_id')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            // Unique Constraints
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'brand_id', 'id']);
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_knowledge_so_brand_95bcdbf6')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_knowledge_sources');
    }
};
