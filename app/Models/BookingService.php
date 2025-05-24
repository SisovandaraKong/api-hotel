<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingService extends Model
{
    protected $fillable = [
        'service_id',
        'service_type_id',
        'quantity',
        'price',
    ];
    

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
