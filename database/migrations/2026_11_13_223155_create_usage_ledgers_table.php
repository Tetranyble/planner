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
        Schema::create('usage_ledgers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->string('usage_kind', 50);
            $table->decimal('quantity', 20, 6);
            $table->string('unit', 20);
            $table->string('source_ref');
            $table->timestamp('recorded_at')->useCurrent();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'usage_kind', 'source_ref'], 'uq_usage_ledger');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_ledgers');
    }
};
