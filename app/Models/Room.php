<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'room_type_id',
        'room_number',
        'desc',
        'room_image',
        'is_active',
    ];

    /**
     * Get the room type that owns the room.
     */
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Get the images for the room.
     */
    public function images()
    {
        return $this->hasMany(RoomImage::class);
    }

    /**
     * Get the booking rooms for the room.
     */
    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class);
    }

    /**
     * Get the ratings for the room through bookings.
     */
    public function ratings()
    {
        return $this->hasManyThrough(
            Rating::class,
            BookingRoom::class,
            'room_id',
            'booking_id',
            'id',
            'booking_id'
        );
    }
}
