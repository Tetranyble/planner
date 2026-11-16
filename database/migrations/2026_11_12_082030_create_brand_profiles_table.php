<?php

use App\Enums\Locale;
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
        Schema::create('brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('brand_id');
            $table->string('positioning')->nullable();
            $table->string('default_locale', 30)->default(Locale::EN_NG->value);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            // Foreign Key Constraints
            $table->unsignedBigInteger('organisation_id');
            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('restrict');

            $table->foreign('updated_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            // Unique Constraints
            $table->unique(['organisation_id', 'brand_id']);
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_profiles_brand_id_e824ae7c');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_profiles_brand_c94df7a6')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_profiles');
    }
};
