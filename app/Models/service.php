<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'available',
        'service_type_id', // Add this line
    ];

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }
}
