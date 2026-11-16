<?php

use App\Enums\Growth\DemoBookingStatus;
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
        Schema::create('demo_bookings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('lead_contact_id')->nullable();

            $table->string('external_booking_id');
            $table->string('booking_source', 50);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default(DemoBookingStatus::BOOKED->value);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('lead_id')->references('id')->on('leads')
                ->onDelete('restrict');

            $table->foreign('lead_contact_id')->references('id')->on('lead_contacts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'booking_source', 'external_booking_id'], 'uq_demo_bookings_ex');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_demo_booking_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_demo_bookings_brand_a925da70')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'lead_id'], 'fk_demo_bookings_lead_id_ff6f2f22')->references(['organisation_id', 'brand_id', 'id'])->on('leads')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'lead_contact_id'], 'fk_demo_bookings_lead_contact_id_fc143b1a')->references(['organisation_id', 'brand_id', 'id'])->on('lead_contacts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_bookings');
    }
};
