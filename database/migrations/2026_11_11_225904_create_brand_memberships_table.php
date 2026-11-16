<?php

use App\Enums\StateEnum;
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
        Schema::create('brand_memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->string('state', 30)->default(StateEnum::ACTIVE->value);

            // User can belong to a brand once. brand_id implies organisation,
            // but including organisation_id in the PK keeps the tenant in the key
            // (helps FK/partition strategies and matches how the table is queried).
            $table->primary(['organisation_id', 'brand_id', 'user_id']);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')->restrictOnDelete();
            $table->foreign('brand_id')
                ->references('id')->on('brands')->restrictOnDelete();
            $table->foreign('user_id')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('role_id')
                ->references('id')->on('roles')->restrictOnDelete();

            $table->timestamps();
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_memberships_brand_518f766a')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'user_id'], 'fk_brand_member_org_member')->references(['organisation_id', 'user_id'])->on('organisation_memberships')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_memberships');
    }
};
