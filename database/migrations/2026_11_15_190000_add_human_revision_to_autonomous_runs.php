<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_agent_autonomous_runs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('resource_revision_count')->default(0)->after('resource_hash');
        });
    }

    public function down(): void
    {
        Schema::table('planner_agent_autonomous_runs', function (Blueprint $table): void {
            $table->dropColumn('resource_revision_count');
        });
    }
};
