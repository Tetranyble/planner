<?php

use App\Enums\Content\ContentPublishingMode;
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
        Schema::create('content_targets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('content_version_id');
            $table->unsignedBigInteger('social_account_id');

            $table->text('platform_caption')->nullable();
            $table->json('payload_json');
            $table->char('payload_hash', 64);
            $table->string('publishing_mode', 20)->default(ContentPublishingMode::AUTOMATIC->value);
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('content_version_id')->references('id')->on('content_versions')
                ->onDelete('restrict');
            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_content_targets_tenant_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_content_targets_brand_d41faba6')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_version_id'], 'fk_content_targets_content_version_i_94091afa')->references(['organisation_id', 'brand_id', 'id'])->on('content_versions')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'social_account_id'], 'fk_content_targets_social_account_id_b00d99d7')->references(['organisation_id', 'brand_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_targets');
    }
};
