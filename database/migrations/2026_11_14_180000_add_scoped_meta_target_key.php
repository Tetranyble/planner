<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_data_deletion_targets', function (Blueprint $table): void {
            $table->unique(['organisation_id', 'id'], 'uq_meta_deletion_target_scope');
        });
    }

    public function down(): void
    {
        Schema::table('meta_data_deletion_targets', function (Blueprint $table): void {
            $table->dropUnique('uq_meta_deletion_target_scope');
        });
    }
};
