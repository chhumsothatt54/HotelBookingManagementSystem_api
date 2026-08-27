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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 50)
                ->unique();
            // Customer
            $table->foreignId('customer_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('hotel_id')
                ->constrained('hotels')
                ->cascadeOnDelete();
            $table->foreignId('room_id')
                ->constrained('rooms')
                ->cascadeOnDelete();
            $table->string('guest_name', 100);
            $table->string('guest_phone', 20);
            $table->string('guest_email', 150);
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('total_guests')
                ->default(1);
            $table->decimal(
                'price_per_night',
                10,
                2
            );
            $table->unsignedInteger('nights');

            $table->decimal(
                'total_amount',
                10,
                2
            );
            $table->enum('status', [
                'pending',
                'confirmed',
                'rejected',
                'checked_in',
                'checked_out',
                'cancelled'
            ])->default('pending');
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
