<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_versions', function (Blueprint $table) {
            // NULL means unknown, NOT false. Existing versions remain unknown.
            $table->boolean('ai_generated')->nullable();
            $table->boolean('includes_sensitive_claim')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_versions', fn (Blueprint $table) => $table->dropColumn(['ai_generated', 'includes_sensitive_claim']));
    }
};
