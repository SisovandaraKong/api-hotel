<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'price',
        'description',
        'capacity',
    ];

    /**
     * Get the rooms for the room type.
     */
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
