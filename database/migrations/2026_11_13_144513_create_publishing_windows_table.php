<?php

use App\Enums\DayOfWeek;
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
        Schema::create('publishing_windows', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('social_account_id');

            $table->unsignedSmallInteger('weekday')->default(DayOfWeek::SUNDAY->value); // 1..7
            $table->time('local_start');
            $table->time('local_end');
            $table->string('timezone', 64);
            $table->boolean('enabled')->default(true);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'social_account_id', 'weekday', 'local_start'],
                'uq_publishing_windows_slot'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_publishing_windows_brand_id_49e479fd');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_publishing_windows_brand_e2ec598b')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'social_account_id'], 'fk_publishing_windows_social_account_id_416c13ae')->references(['organisation_id', 'brand_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publishing_windows');
    }
};
