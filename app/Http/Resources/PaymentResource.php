<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'total_payment' => (float) $this->total_payment,
            'method_payment' => $this->method_payment,
            'transaction_id' => $this->transaction_id,
            'payment_status' => $this->payment_status,
            'date_payment' => $this->date_payment->format('Y-m-d H:i:s'),
            'booking' => $this->whenLoaded('booking', function() {
                return [
                    'id' => $this->booking->id,
                    'booking_status' => $this->booking->booking_status,
                    'check_in_date' => $this->booking->check_in_date,
                    'check_out_date' => $this->booking->check_out_date,
                    'user' => $this->booking->user ? [
                        'id' => $this->booking->user->id,
                        'name' => $this->booking->user->name,
                        'email' => $this->booking->user->email,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
