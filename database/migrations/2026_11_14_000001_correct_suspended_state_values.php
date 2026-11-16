<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['organisations', 'brands', 'brand_audience_segments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'status')) {
                DB::table($table)->where('status', 'SUSPENNDED')->update(['status' => 'SUSPENDED']);
            }
        }

        foreach (['organisation_memberships', 'brand_memberships'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'state')) {
                DB::table($table)->where('state', 'SUSPENNDED')->update(['state' => 'SUSPENDED']);
            }
        }
    }

    public function down(): void
    {
        // Data correction is intentionally irreversible.
    }
};
