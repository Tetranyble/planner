<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_operational_alert_events', fn (Blueprint $t) => $t->unique(['organisation_id', 'brand_id', 'id'], 'uq_planner_alert_event_scope_id'));
    }

    public function down(): void
    {
        Schema::table('planner_operational_alert_events', fn (Blueprint $t) => $t->dropUnique('uq_planner_alert_event_scope_id'));
    }
};
