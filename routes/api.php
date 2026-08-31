<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\HotelControler;
use App\Http\Controllers\HotelManagerController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Email Verification
Route::post('/auth/mail/send', [AuthController::class, 'sendMail']);
Route::post('/auth/mail/resend', [AuthController::class, 'resendMail']);
Route::post('/auth/mail/confirm', [AuthController::class, 'confirmMail']);

// Forgot Password
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/forgot-password/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/auth/forgot-password/confirm-otp', [AuthController::class, 'confirmOtp']);
Route::post('/auth/forgot-password/set-new-password', [AuthController::class, 'setNewPassword']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Get logged-in user
    Route::get('/me', function (Request $request) {
        return response()->json([
            'user' => $request->user()
        ]);
    });

    // Logout
    Route::post('/logout', [
        AuthController::class,
        'logout'
    ]);

    // Change Password
    Route::post('/change-password', [
        AuthController::class,
        'changePassword'
    ]);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'admin'
])->prefix('admin')->group(function () {

    Route::get('/dashboard', [
        AdminController::class,
        'dashboard'
    ]);

    // Route::get('/users', [
    //     AdminController::class,
    //     'users'
    // ]);

    // Route::get('/users/{id}', [
    //     AdminController::class,
    //     'showUser'
    // ]);

    // Route::patch('/users/{id}/status', [
    //     AdminController::class,
    //     'updateUserStatus'
    // ]);

    // Route::get('/managers', [
    //     AdminController::class,
    //     'managers'
    // ]);

    // Route::patch('/managers/{id}/status', [
    //     AdminController::class,
    //     'updateManagerStatus'
    // ]);

    // Route::get('/hotels', [
    //     AdminController::class,
    //     'hotels'
    // ]);

    // Route::get('/hotels/{id}', [
    //     AdminController::class,
    //     'showHotel'
    // ]);

    // Route::patch('/hotels/{id}/status', [
    //     AdminController::class,
    //     'updateHotelStatus'
    // ]);

    // Route::get('/room-types', [
    //     AdminController::class,
    //     'roomTypes'
    // ]);

    // Route::get('/rooms', [
    //     AdminController::class,
    //     'rooms'
    // ]);

    // Route::get('/amenities', [
    //     AdminController::class,
    //     'amenities'
    // ]);

    // Route::get('/bookings', [
    //     AdminController::class,
    //     'bookings'
    // ]);

    // Route::get('/bookings/{id}', [
    //     AdminController::class,
    //     'showBooking'
    // ]);

    // Route::get('/payments', [
    //     AdminController::class,
    //     'payments'
    // ]);

    // Route::get('/reviews', [
    //     AdminController::class,
    //     'reviews'
    // ]);

    // Route::patch('/reviews/{id}/status', [
    //     AdminController::class,
    //     'updateReviewStatus'
    // ]);

    // Route::get('/reports/revenue', [
    //     AdminController::class,
    //     'revenueReport'
    // ]);

    // Route::get('/reports/occupancy', [
    //     AdminController::class,
    //     'occupancyReport'
    // ]);

    // Route::get('/notifications', [
    //     AdminController::class,
    //     'notifications'
    // ]);

    // Route::get('/audit-logs', [
    //     AdminController::class,
    //     'auditLogs'
    // ]);

});

/*
|--------------------------------------------------------------------------
| Hotel Manager Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'manager'
])->prefix('manager')->group(function () {

    // Dashboard
    Route::get('/dashboard', [
        HotelManagerController::class,
        'dashboard'
    ]);

    // Hotel
    Route::get('/hotel', [
        HotelManagerController::class,
        'myHotel'
    ]);

    // //  បន្ថែម Route មួយនេះសម្រាប់បង្កើត Hotel ថ្មី
    // Route::post('/hotel', [
    //     HotelManagerController::class,
    //     'storeHotel'
    // ]);

    Route::put('/hotel', [
        HotelManagerController::class,
        'updateHotel'
    ]);

    // Room Types
    Route::get('/room-types', [
        HotelManagerController::class,
        'roomTypes'
    ]);

    Route::post('/room-types', [
        HotelManagerController::class,
        'storeRoomType'
    ]);

    Route::put('/room-types/{id}', [
        HotelManagerController::class,
        'updateRoomType'
    ]);

    Route::delete('/room-types/{id}', [
        HotelManagerController::class,
        'deleteRoomType'
    ]);

    // Rooms
    Route::get('/rooms', [
        HotelManagerController::class,
        'rooms'
    ]);

    Route::post('/rooms', [
        HotelManagerController::class,
        'storeRoom'
    ]);

    Route::put('/rooms/{id}', [
        HotelManagerController::class,
        'updateRoom'
    ]);

    Route::delete('/rooms/{id}', [
        HotelManagerController::class,
        'deleteRoom'
    ]);

    // Amenities
    Route::get('/amenities', [
        HotelManagerController::class,
        'amenities'
    ]);

    Route::post('/amenities/{amenityId}', [
        HotelManagerController::class,
        'attachAmenity'
    ]);

    Route::delete('/amenities/{amenityId}', [
        HotelManagerController::class,
        'detachAmenity'
    ]);

    // Bookings
    Route::get('/bookings', [
        HotelManagerController::class,
        'bookings'
    ]);

    Route::get('/bookings/{id}', [
        HotelManagerController::class,
        'showBooking'
    ]);

    Route::put('/bookings/{id}/status', [
        HotelManagerController::class,
        'updateBookingStatus'
    ]);

    // Reviews
    Route::get('/reviews', [
        HotelManagerController::class,
        'reviews'
    ]);

    // Reports
    Route::get('/reports/revenue', [
        HotelManagerController::class,
        'revenueReport'
    ]);

    Route::get('/reports/occupancy', [
        HotelManagerController::class,
        'occupancyReport'
    ]);

});

/*
|--------------------------------------------------------------------------
| Customer Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'customer'
])->prefix('customer')->group(function () {

    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Welcome Customer'
        ]);
    });

    

});
