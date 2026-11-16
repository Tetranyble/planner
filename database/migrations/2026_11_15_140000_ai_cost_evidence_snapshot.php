<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_ai_usage_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('estimated_micro_usd')->nullable();
            $table->string('cost_rate_revision', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('brand_ai_usage_reservations', fn (Blueprint $table) => $table->dropColumn(['estimated_micro_usd', 'cost_rate_revision']));
    }
};
