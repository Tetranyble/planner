<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_targets', function (Blueprint $table): void {
            $table->unique(['organisation_id', 'brand_id', 'content_version_id', 'social_account_id', 'payload_hash'], 'uq_content_target_dedupe');
        });
    }

    public function down(): void
    {
        Schema::table('content_targets', function (Blueprint $table): void {
            $table->dropUnique('uq_content_target_dedupe');
        });
    }
};
