<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'gender' => $this->gender,
            // Use Storage::url() to generate the correct S3 URL
            'avatar' => $this->avatar ? 'https://romsaydev.s3.us-east-1.amazonaws.com/' . ltrim($this->avatar, '/') : null,
            'role' => $this->role ? [
                'id' => $this->role_id,
                'name' => $this->role->name,
            ] : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
