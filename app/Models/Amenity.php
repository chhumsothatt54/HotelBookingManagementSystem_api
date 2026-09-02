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

<<<<<<< HEAD
    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }
=======
>>>>>>> 47061af7a86db04415ec06906469d5e8b2df2019
}
