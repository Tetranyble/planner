<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_ai_usage_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('provider_input_tokens')->nullable();
            $table->unsignedBigInteger('provider_output_tokens')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('brand_ai_usage_reservations', function (Blueprint $table): void {
            $table->dropColumn(['provider_input_tokens', 'provider_output_tokens']);
        });
    }
};
