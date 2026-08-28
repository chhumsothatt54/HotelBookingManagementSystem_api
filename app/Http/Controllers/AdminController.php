<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(){

        $totalUsers = User::count();

        $totalCustomers = User::where('role', 'customer')->count();

        $totalManagers = User::where(
            'role',
            'hotel_manager'
        )->count();

        $totalHotels = Hotel::count();
        $pendingHotels = Hotel::where(
            'status',
            'pending'
        )->count();

        $totalBookings = Booking::count();

        $totalPayments = Payment::where('status','paid')->count();
        $totalRevenue = Payment::where(
            'status',
            'paid'
        )->sum('amount');

        return response()->json([
            'result'=>true,
            'message'=>'Admin Dashboard',
            'data'=>[
                'total_users'=>$totalUsers,
                'total_customers'=>$totalCustomers,
                'total_manager'=>$totalManagers,
                'total_hotels'=>$totalHotels,
                'pending_hotels'=>$pendingHotels,
                'total_bookings'=>$totalBookings,
                'total_payments'=>$totalPayments,
                'total_revenue'=>$totalRevenue,
            ]
        ]);
    }
}
