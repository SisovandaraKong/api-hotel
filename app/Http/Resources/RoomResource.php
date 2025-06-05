<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'desc' => $this->desc,
            'room_image' => $this->whenLoaded('roomType', function () {
                return $this->roomType && $this->roomType->image
                    ? 'https://romsaydev.s3.us-east-1.amazonaws.com/' . $this->roomType->image
                    : null;
            }, null),
            'is_active' => (bool) $this->is_active,
            'room_type' => $this->whenLoaded('roomType', function() {
                return [
                    'id' => $this->roomType->id,
                    'type' => $this->roomType->type,
                    'price' => (float) $this->roomType->price,
                    'capacity' => $this->roomType->capacity,
                ];
            }),
            'images' => $this->whenLoaded('images', function() {
                return $this->images->map(function($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => asset('storage/' . $image->image_url)
                    ];
                });
            }, []),
            'ratings' => $this->whenLoaded('ratings', function() {
                return [
                    'average' => $this->ratings->avg('rating') ? round($this->ratings->avg('rating'), 1) : 0,
                    'count' => $this->ratings->count(),
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
