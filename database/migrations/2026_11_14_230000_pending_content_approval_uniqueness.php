<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive, MySQL 8+: one outstanding approval per immutable target. */
return new class extends Migration
{
    public function up(): void
    {
        // Do not silently remove historical duplicate pending reviews on deployment.
        $duplicates = DB::table('approval_requests')->select('organisation_id', 'brand_id', 'content_target_id')
            ->selectRaw('COUNT(*) AS pending_count')->where('status', 'PENDING')
            ->groupBy('organisation_id', 'brand_id', 'content_target_id')->havingRaw('COUNT(*) > 1')->first();
        if ($duplicates) {
            throw new RuntimeException('Existing duplicate PENDING content approval requests must be reviewed before applying the unique index.');
        }
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('pending_target_marker')
                ->nullable()->storedAs("CASE WHEN status = 'PENDING' THEN content_target_id ELSE NULL END");
            $table->unique(['organisation_id', 'brand_id', 'pending_target_marker'], 'uq_approval_one_pending_target');
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropUnique('uq_approval_one_pending_target');
            $table->dropColumn('pending_target_marker');
        });
    }
};
