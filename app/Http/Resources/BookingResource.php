<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'booking_status' => $this->booking_status,
            'check_in_date' => $this->check_in_date->toIso8601String(),
            'check_out_date' => $this->check_out_date->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ];
            }),
            'rooms' => $this->whenLoaded('bookingRooms', function () {
                return $this->bookingRooms->map(function ($bookingRoom) {
                    return [
                        'id' => $bookingRoom->room->id,
                        'room_number' => $bookingRoom->room->room_number,
                        'room_type' => [
                            'id' => $bookingRoom->room->roomType->id,
                            'type' => $bookingRoom->room->roomType->type,
                            'price' => (float) $bookingRoom->room->roomType->price,
                        ],
                    ];
                });
            }, []),
            'payment' => $this->whenLoaded('payment', function () {
                return [
                    'id' => $this->payment->id,
                    'total_payment' => (float) $this->payment->total_payment,
                    'method_payment' => $this->payment->method_payment,
                    'transaction_id' => $this->payment->transaction_id,
                    'payment_status' => $this->payment->payment_status,
                    'receipt_url' => $this->payment->receipt_url,  // added here
                    'date_payment' => $this->payment->date_payment ? $this->payment->date_payment->toIso8601String() : null,
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
