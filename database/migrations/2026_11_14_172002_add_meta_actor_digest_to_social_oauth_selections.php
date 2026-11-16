<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_oauth_selections', function (Blueprint $table): void {
            // Indexed lookup avoids scanning encrypted grants on new Meta authorizations.
            // Legacy pending grants expire in ten minutes and are handled by fallback scan.
            $table->char('meta_actor_digest', 64)->nullable()->index('idx_oauth_selection_meta_actor');
        });
    }

    public function down(): void
    {
        Schema::table('social_oauth_selections', function (Blueprint $table): void {
            $table->dropIndex('idx_oauth_selection_meta_actor');
            $table->dropColumn('meta_actor_digest');
        });
    }
};
