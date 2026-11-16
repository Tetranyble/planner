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
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->string('name');
            $table->string('timezone')->default(TimeZone::AFRICA_LAGOS->value);
            $table->string('status', 30)->default(StatusEnum::ACTIVE->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
