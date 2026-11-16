<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_connections', function (Blueprint $table): void {
            // Never hold a database transaction open during provider HTTP I/O.
            $table->char('refresh_lease_token', 36)->nullable();
            $table->timestamp('refresh_lease_until')->nullable();
            $table->timestamp('refresh_retry_at')->nullable();
            $table->timestamp('last_refresh_at')->nullable();
            $table->string('last_refresh_result', 40)->nullable();
            $table->unsignedInteger('refresh_attempts')->default(0);
            $table->index(['status', 'token_expires_at', 'refresh_retry_at'], 'idx_social_refresh_due');
            $table->index('refresh_lease_until', 'idx_social_refresh_lease');
        });
    }

    public function down(): void
    {
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->dropIndex('idx_social_refresh_due');
            $table->dropIndex('idx_social_refresh_lease');
            $table->dropColumn([
                'refresh_lease_token', 'refresh_lease_until', 'refresh_retry_at',
                'last_refresh_at', 'last_refresh_result', 'refresh_attempts',
            ]);
        });
    }
};
