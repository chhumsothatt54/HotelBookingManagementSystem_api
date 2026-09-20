<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class AutoCheckoutBookings extends Command
{
    protected $signature = 'bookings:auto-checkout';

    protected $description = 'Automatically check out checked-in bookings when checkout time arrives';

    public function handle()
    {
        $bookings = Booking::with('room')
            ->where('status', 'checked_in')
            ->where('check_out', '<=', now())
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings need automatic checkout.');

            return Command::SUCCESS;
        }

        foreach ($bookings as $booking) {

            DB::transaction(function () use ($booking) {

                // Booking: checked_in → checked_out
                $booking->update([
                    'status' => 'checked_out',
                ]);

                // Room: inactive → maintenance
                if ($booking->room) {
                    $booking->room->update([
                        'status' => 'maintenance',
                    ]);
                }
            });

            $this->info(
                "Booking {$booking->booking_number} automatically checked out."
            );
        }

        $this->info(
            "{$bookings->count()} booking(s) automatically checked out."
        );

        return Command::SUCCESS;
    }
}