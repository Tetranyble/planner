<?php

use App\Enums\StatusEnum;
use App\Enums\TimeZone;
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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->string('name');
            $table->string('website_url')->nullable();
            $table->string('timezone', 50)->default(TimeZone::AFRICA_LAGOS->value);
            $table->string('status', 30)->default(StatusEnum::ACTIVE->value);
            $table->json('settings')->nullable();

            // Foreign Key Constraint
            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('restrict');

            // Unique Constraints
            $table->unique(['organisation_id', 'id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
