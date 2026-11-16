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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->uuid()->nullable();
            $table->string('name');
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('state_code')->nullable();
            $table->string('state_name')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('country_code', 5);
            $table->string('country_name');
            $table->decimal('latitude', 16, 10)->nullable();
            $table->decimal('longitude', 16, 10)->nullable();
            $table->string('wikiDataId')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->foreign('state_id')->references('id')->on('states')
                ->nullOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')
                ->nullOnDelete();
            $table->unique(['id', 'state_id', 'country_id'], 'uq_city_state_country_hierarchy');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
