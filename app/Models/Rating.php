<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_id',
        'booking_id',
        'rating',
        'comment',
    ];

    /**
     * Get the user that owns the rating.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    /**
     * Get the booking that owns the rating.
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
