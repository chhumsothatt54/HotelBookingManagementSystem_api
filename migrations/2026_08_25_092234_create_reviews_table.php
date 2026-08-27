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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('hotel_id')
                ->constrained('hotels')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');

            $table->text('comment')->nullable();
            $table->enum('status', [
                'pending',
                'approved',
                'hidden'
            ])->default('pending');
            $table->timestamps();
            // 1 Booking = 1 Review
            $table->unique('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
