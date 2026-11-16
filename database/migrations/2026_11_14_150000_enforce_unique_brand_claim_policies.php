<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Requires preflight duplicate check on any database with existing claim-policy rows. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_claim_policies', function (Blueprint $table): void {
            $table->unique(['organisation_id', 'brand_id'], 'uq_brand_claim_policies_one_per_brand');
        });
    }

    public function down(): void
    {
        Schema::table('brand_claim_policies', function (Blueprint $table): void {
            $table->dropUnique('uq_brand_claim_policies_one_per_brand');
        });
    }
};
