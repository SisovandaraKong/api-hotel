<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'price' => $this->price,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'image_url' => $this->image ? asset('storage/' . $this->image) : null,
        ];
    }
}
