<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomAmenity extends Model
{
    use HasFactory;
    protected $table = 'room_amenities';

    protected $fillable = [
        'room_id',
        'amenity_id',
    ];

    public $incrementing = false;

    protected $primaryKey = null;

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }
}
