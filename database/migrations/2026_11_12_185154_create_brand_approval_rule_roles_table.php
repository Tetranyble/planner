<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('brand_approval_rule_roles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('approval_rule_id');

            $table->unsignedBigInteger('reviewer_role_id'); // ReviewerRole

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('approval_rule_id')->references('id')->on('brand_approval_rules')
                ->onDelete('restrict');
            $table->foreign('reviewer_role_id')->references('id')->on('roles')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'brand_id', 'approval_rule_id', 'reviewer_role_id'],
                'uq_brand_approval_rule_roles'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_approval_rul_brand_id_8c51e778');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_approval_rul_brand_2ad57bac')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'approval_rule_id'], 'fk_brand_approval_rul_approval_rule_id_7a7246b9')->references(['organisation_id', 'brand_id', 'id'])->on('brand_approval_rules')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_approval_rule_roles');
    }
};
