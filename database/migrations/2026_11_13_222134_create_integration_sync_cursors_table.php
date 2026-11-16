<?php

use App\Enums\Operation\IntegrationSyncState;
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
        Schema::create('integration_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('social_account_id');

            $table->string('sync_kind', 50);
            $table->string('cursor_ref')->nullable();
            $table->timestamp('watermark_at')->nullable();
            $table->string('state', 20)->default(IntegrationSyncState::READY->value);
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error_code')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'social_account_id', 'sync_kind'], 'uq_integration_cursors');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_integration_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_integration_sync_c_brand_9ff599d3')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'social_account_id'], 'fk_integration_sync_c_social_account_id_00e59d8c')->references(['organisation_id', 'brand_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_sync_cursors');
    }
};
