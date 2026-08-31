<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Amenity extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'icon',
        'description',
        'status',
    ];

    public function rooms()
    {
        return $this->belongsToMany(
            Room::class,
            'room_amenities'
        )->withTimestamps();
    }
}
