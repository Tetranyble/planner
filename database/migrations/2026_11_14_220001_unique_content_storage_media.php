<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->unique(['organisation_id', 'brand_id', 'storage_media_id'], 'uq_content_asset_storage_brand');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropUnique('uq_content_asset_storage_brand');
        });
    }
};
