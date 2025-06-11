<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Charge;
use Exception;

class PaymentTestController extends Controller
{
    public function showForm()
    {
        return view('payment.form'); // Ensure you have a view named 'payment.form'
    }

    public function process(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $charge = Charge::create([
            'amount' => 1000, // 10.00 USD (amount is in cents)
            'currency' => 'usd',
            'source' => $request->stripeToken,
            'description' => 'Test Payment from Laravel',
        ]);

        return redirect()->back()->with('success', 'Payment successful!');
    }

    //api endpoint for React frontend to charge
    public function charge(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string',
            'token' => 'required|string',
        ]);

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $charge = Charge::create([
                'amount' => $request->amount * 100, // convert to cents
                'currency' => $request->currency,
                'source' => $request->token,
                'description' => 'Payment from React frontend',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment successful',
                'charge' => $charge,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

