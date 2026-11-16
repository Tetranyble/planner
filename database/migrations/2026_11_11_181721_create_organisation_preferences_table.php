<?php

use App\Enums\Currency;
use App\Enums\DateFormat;
use App\Enums\DayOfWeek;
use App\Enums\Locale;
use App\Enums\TimeFormat;
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

        Schema::create('organisation_preferences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('default_locale', 30)->default(Locale::EN_NG->value);
            $table->string('reporting_currency', 3)->default(Currency::NGN->value);
            $table->smallInteger('week_starts_on')->default(DayOfWeek::MONDAY->value);
            $table->string('date_display_format', 20)->default(DateFormat::DMY_SLASH->value);
            $table->string('time_display_format')->default(TimeFormat::TIME_24_HOUR->value);
            $table->unsignedBigInteger('organisation_id')->unique();
            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_preferences');
    }
};
