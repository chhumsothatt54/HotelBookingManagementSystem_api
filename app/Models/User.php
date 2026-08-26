<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'avatar',
        'email_verified_at',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Hotel Manager
    public function hotels()
    {
        return $this->hasMany(Hotel::class, 'manager_id');
    }

    // Customer
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function refundRequests()
    {
        return $this->hasMany(RefundRequest::class, 'customer_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    public function emailVerifications()
    {
        return $this->hasMany(EmailVerification::class);
    }

    public function passwordResetOtps()
    {
        return $this->hasMany(PasswordResetOtp::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }
}
