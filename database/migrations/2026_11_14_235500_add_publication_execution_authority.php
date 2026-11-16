<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publication_jobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('armed_by_user_id')->nullable()->after('approval_request_id');
            $table->timestamp('armed_at')->nullable()->after('armed_by_user_id');
            $table->foreign('armed_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['state', 'run_at', 'id'], 'idx_publication_execution_due');
        });
        // An operation key is globally unique within the tenant, not merely within one attempt number.
        Schema::table('publication_attempts', function (Blueprint $table): void {
            $table->unique(['organisation_id', 'operation_key'], 'uq_publication_attempt_operation');
        });
    }

    public function down(): void
    {
        Schema::table('publication_attempts', fn (Blueprint $table) => $table->dropUnique('uq_publication_attempt_operation'));
        Schema::table('publication_jobs', function (Blueprint $table): void {
            $table->dropIndex('idx_publication_execution_due');
            $table->dropForeign(['armed_by_user_id']);
            $table->dropColumn(['armed_by_user_id', 'armed_at']);
        });
    }
};
