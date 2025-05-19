<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Process a payment for a booking.
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|integer|exists:bookings,id',
            'method_payment' => 'required|string|in:credit_card,paypal,cash',
            'total_payment' => 'required|numeric|min:0',
            'card_number' => 'required_if:method_payment,credit_card|string|max:16',
            'card_expiry' => 'required_if:method_payment,credit_card|string|max:7',
            'card_cvv' => 'required_if:method_payment,credit_card|string|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation error',
                'data' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $booking = Booking::find($request->booking_id);

        // Check if booking belongs to user
        if ($booking->user_id !== $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Unauthorized to process payment for this booking',
                'data' => null
            ], 403);
        }

        // Check if booking already has a payment
        if ($booking->payment) {
            return response()->json([
                'result' => false,
                'message' => 'Booking already has a payment',
                'data' => null
            ], 422);
        }

        // Process payment based on method
        $paymentStatus = 'pending';
        $transactionId = null;

        // In a real application, you would integrate with a payment gateway here
        // For this example, we'll simulate payment processing
        switch ($request->method_payment) {
            case 'credit_card':
                // Simulate credit card payment processing
                $transactionId = 'CC_' . uniqid();
                $paymentStatus = 'completed';
                break;

            case 'paypal':
                // Simulate PayPal payment processing
                $transactionId = 'PP_' . uniqid();
                $paymentStatus = 'completed';
                break;

            case 'cash':
                // Cash payments are marked as pending until confirmed by admin
                $transactionId = 'CASH_' . uniqid();
                $paymentStatus = 'pending';
                break;
        }

        // Create payment record
        $payment = new Payment();
        $payment->booking_id = $booking->id;
        $payment->total_payment = $request->total_payment;
        $payment->method_payment = $request->method_payment;
        $payment->transaction_id = $transactionId;
        $payment->payment_status = $paymentStatus;
        $payment->date_payment = now();
        $payment->save();

        // Update booking status if payment is completed
        if ($paymentStatus === 'completed') {
            $booking->booking_status = 'confirmed';
            $booking->save();
        }

        return response()->json([
            'result' => true,
            'message' => 'Payment processed successfully',
            'data' => new PaymentResource($payment)
        ]);
    }

    /**
     * Get available payment methods.
     */
    public function getPaymentMethods()
    {
        return response()->json([
            'result' => true,
            'message' => 'Payment methods retrieved successfully',
            'data' => [
                'methods' => [
                    [
                        'id' => 'credit_card',
                        'name' => 'Credit Card',
                        'description' => 'Pay securely with your credit card',
                        'requires_additional_info' => true
                    ],
                    [
                        'id' => 'paypal',
                        'name' => 'PayPal',
                        'description' => 'Pay with your PayPal account',
                        'requires_additional_info' => false
                    ],
                    [
                        'id' => 'cash',
                        'name' => 'Cash',
                        'description' => 'Pay in cash at check-in',
                        'requires_additional_info' => false
                    ]
                ]
            ]
        ]);
    }
}
