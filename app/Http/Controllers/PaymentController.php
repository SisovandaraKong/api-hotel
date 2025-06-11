<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Charge;
use Exception;

class PaymentController extends Controller{

// Inside your controller...
public function processPayment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'booking_id' => 'required|integer|exists:bookings,id',
        'method_payment' => 'required|string|in:credit_card,paypal,cash',
        'total_payment' => 'required|numeric|min:0',
        'stripe_token' => 'required_if:method_payment,credit_card|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'result' => false,
            'message' => 'Validation error',
            'data' => $validator->errors()
        ], 422);
    }

    $user = $request->user();

    if ($user->role_id !== 1) {
        return response()->json([
            'result' => false,
            'message' => 'Only users are allowed to make payments',
            'data' => null
        ], 403);
    }

    $booking = Booking::find($request->booking_id);

    if (!$booking) {
        return response()->json([
            'result' => false,
            'message' => 'Booking not found',
            'data' => null
        ], 404);
    }

    if ($booking->user_id !== $user->id) {
        return response()->json([
            'result' => false,
            'message' => 'Unauthorized to process payment for this booking',
            'data' => null
        ], 403);
    }

    if ($booking->payment) {
        return response()->json([
            'result' => false,
            'message' => 'Booking already has a payment',
            'data' => null
        ], 422);
    }

    $paymentStatus = 'pending';
    $transactionId = null;
    $stripeCharge = null;

    try {
        switch ($request->method_payment) {
            case 'credit_card':
                Stripe::setApiKey(config('services.stripe.secret'));

                $charge = Charge::create([
                    'amount' => $request->total_payment * 100, // Stripe uses cents
                    'currency' => 'usd',
                    'source' => $request->stripe_token,
                    'description' => 'Payment for booking #' . $booking->id,
                ]);

                $transactionId = $charge->id;
                $paymentStatus = $charge->status === 'succeeded' ? 'completed' : 'failed';
                $stripeCharge = $charge;
                break;

            case 'paypal':
                $transactionId = 'PP_' . uniqid();
                $paymentStatus = 'completed';
                break;

            case 'cash':
                $transactionId = 'CASH_' . uniqid();
                $paymentStatus = 'pending';
                break;
        }
    } catch (Exception $e) {
        return response()->json([
            'result' => false,
            'message' => 'Payment failed: ' . $e->getMessage(),
            'data' => null
        ], 500);
    }

    $payment = new Payment();
    $payment->booking_id = $booking->id;
    $payment->total_payment = $request->total_payment;
    $payment->method_payment = $request->method_payment;
    $payment->transaction_id = $transactionId;
    $payment->payment_status = $paymentStatus;
    $payment->date_payment = now();
    $payment->save();

    if ($paymentStatus === 'completed') {
        $booking->booking_status = 'confirmed';
        $booking->save();
    }

    $response = [
        'result' => true,
        'message' => 'Payment processed successfully',
        'data' => new PaymentResource($payment)
    ];

    // Add Stripe charge to response if it's a credit card payment
    if ($stripeCharge) {
        $response['stripe_charge'] = $stripeCharge;
    }

    return response()->json($response);
}

 //get all payments
public function getAllPayments(Request $request)
{
    $user = $request->user();

    // Only role_id = 1 (User) can see their own payments
    if ($user->role_id === 1) {
        $payments = Payment::whereHas('booking', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['booking.user'])->get();
    }
    // Admins/Super Admins can see all payments
    else {
        $payments = Payment::with(['booking.user'])->get();
    }

    return response()->json([
        'result' => true,
        'message' => 'Payments retrieved successfully',
        'data' => PaymentResource::collection($payments),
    ]);
}
}
