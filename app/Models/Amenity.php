<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
