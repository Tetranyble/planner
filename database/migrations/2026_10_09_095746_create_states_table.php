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
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('country_code', 5);
            $table->string('country_name');
            $table->string('state_code')->nullable();
            $table->string('type')->nullable();
            $table->decimal('latitude', 16, 10)->nullable();
            $table->decimal('longitude', 16, 10)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('country_id')->references('id')->on('countries')
                ->nullOnDelete();
            $table->unique(['id', 'country_id'], 'uq_state_country_hierarchy');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
