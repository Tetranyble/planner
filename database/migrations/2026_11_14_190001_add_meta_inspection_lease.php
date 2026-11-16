<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->char('inspection_lease_token', 36)->nullable();
            $table->timestamp('inspection_lease_until')->nullable();
            $table->timestamp('inspection_retry_at')->nullable();
            $table->timestamp('data_access_expires_at')->nullable();
            $table->string('last_inspection_result', 40)->nullable();
            $table->unsignedSmallInteger('inspection_attempts')->default(0);
            $table->index(['provider', 'status', 'last_verified_at', 'inspection_retry_at'], 'idx_meta_inspection_due');
        });
    }

    public function down(): void
    {
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->dropIndex('idx_meta_inspection_due');
            $table->dropColumn(['inspection_lease_token', 'inspection_lease_until', 'inspection_retry_at',
                'data_access_expires_at', 'last_inspection_result', 'inspection_attempts']);
        });
    }
};
