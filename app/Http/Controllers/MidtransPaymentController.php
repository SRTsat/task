<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MidtransPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        // Validate required fields (include plan_id which was missing)
        $validated = $request->validate([
            'plan_id' => 'required|integer',
            'transaction_status' => 'required|string',
            'order_id' => 'required|string',
            'billing_cycle' => 'sometimes|string',
            'coupon_code' => 'nullable|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();

            // Server/secret key required to verify callbacks
            if (empty($settings['payment_settings']['midtrans_secret_key'])) {
                Log::warning('Midtrans processPayment called but secret key missing', ['user_id' => auth()->id(), 'plan_id' => $plan->id]);
                return back()->withErrors(['error' => __('Midtrans not configured')]);
            }

            if (in_array($validated['transaction_status'], ['capture', 'settlement'])) {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'] ?? 'monthly',
                    'payment_method' => 'midtrans',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['order_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);
        } catch (\Exception $e) {
            Log::error('Midtrans processPayment exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return handlePaymentError($e, 'midtrans');
        }
    }

    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|integer',
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'coupon_code' => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            $paymentSettings = $settings['payment_settings'] ?? [];

            $midtransKey = $paymentSettings['midtrans_secret_key'] ?? env('MIDTRANS_SECRET_KEY');
            $midtransClient = $paymentSettings['midtrans_client_key'] ?? env('MIDTRANS_CLIENT_KEY');

            if (empty($midtransKey) || empty($midtransClient)) {
                Log::error('Midtrans not configured. Settings:', $paymentSettings);
                return response()->json(['error' => __('Midtrans not configured')], 400);
            }

            $user = auth()->user();
            $orderId = 'plan_' . $plan->id . '_' . ($user->id ?? 'guest') . '_' . time();

            // Ensure final price as integer (IDR whole number). Round if needed.
            $amount = intval(round($pricing['final_price']));

            $paymentData = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $amount
                ],
                'credit_card' => [
                    'secure' => true
                ],
                'customer_details' => [
                    'first_name' => $user->name ?? 'Customer',
                    'email' => $user->email ?? null,
                ],
                'item_details' => [
                    [
                        'id' => $plan->id,
                        'price' => $amount,
                        'quantity' => 1,
                        'name' => $plan->name
                    ]
                ]
            ];

            $midtransKey = $paymentSettings['midtrans_secret_key'] ?? $paymentSettings['midtrans_secret_key'] ?? null;
            $midtransMode = $paymentSettings['midtrans_mode'] ?? ($paymentSettings['midtrans_is_production'] ?? 'sandbox');

            // Create a pending plan order using PlanOrder model
            $planOrder = PlanOrder::create([
                'user_id' => $user->id ?? null,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'] ?? 'monthly',
                'payment_method' => 'midtrans',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $orderId,
                'status' => 'pending',
                'original_price' => $pricing['original_price'] ?? $plan->price,
                'final_price' => $pricing['final_price'] ?? $plan->price,
            ]);
            logger()->info('Preparing to create snap token', [
                'midtrans_secret_key' => substr($midtransKey ?? '', 0, 8) . '********',
                'midtrans_mode' => $midtransMode,
                'amount' => $amount,
                'order_id' => $orderId,
            ]);


            $result = $this->createSnapToken($paymentData, [
                'midtrans_secret_key' => env('MIDTRANS_SECRET_KEY', $midtransKey),
                'midtrans_mode' => 'sandbox'
            ]);

            Log::info('PAYMENT SETTINGS', $settings['payment_settings']);

            if ($result && is_array($result)) {
                $baseUrl = ($settings['payment_settings']['midtrans_mode'] ?? 'sandbox') === 'live'
                    ? 'https://api.midtrans.com'
                    : 'https://api.sandbox.midtrans.com';

                return response()->json([
                    'success' => true,
                    'snap_token' => $result['token'] ?? null,
                    'redirect_url' => $result['redirect_url'] ?? ($result['token'] ? $baseUrl . '/snap/v1/transactions/' . $result['token'] : null),
                    'order_id' => $orderId,
                    'plan_order_id' => $planOrder->id ?? null,
                ]);
            }

            throw new \Exception(__('Failed to create Midtrans snap token'));
        } catch (\Exception $e) {
            Log::error('Midtrans createPayment exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => __('Payment creation failed'), 'message' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request)
    {
        try {
            $orderId = $request->input('order_id') ?? $request->input('order_id');
            $transactionStatus = $request->input('transaction_status') ?? $request->input('transaction_status');
            $statusCode = $request->input('status_code') ?? $request->input('status_code');
            $grossAmount = $request->input('gross_amount') ?? $request->input('gross_amount');
            $signatureKey = $request->input('signature_key') ?? $request->input('signature_key');

            // Verify signature_key per Midtrans docs: sha512(order_id + status_code + gross_amount + server_key)
            $settings = getPaymentGatewaySettings();
            $serverKey = $settings['payment_settings']['midtrans_secret_key'] ?? null;

            if (!$serverKey) {
                return response()->json(['error' => 'Midtrans not configured'], 400);
            }

            $localSignature = hash('sha512', ($orderId ?? '') . ($statusCode ?? '') . ($grossAmount ?? '') . $serverKey);

            if (!hash_equals($localSignature, (string)($signatureKey ?? ''))) {
                // Invalid signature: ignore or log
                \Log::warning('Midtrans signature mismatch', ['order_id' => $orderId]);
                return response()->json(['error' => 'invalid signature'], 403);
            }

            if ($orderId && in_array($transactionStatus, ['capture', 'settlement'])) {
                // find pending plan order by payment_id
                $planOrder = PlanOrder::where('payment_id', $orderId)->first();

                if ($planOrder) {
                    $plan = Plan::find($planOrder->plan_id);
                    $user = \App\Models\User::find($planOrder->user_id);

                    if ($plan && $user) {
                        // mark plan order approved
                        $planOrder->status = 'approved';
                        $planOrder->save();

                        processPaymentSuccess([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'billing_cycle' => $planOrder->billing_cycle ?? 'monthly',
                            'payment_method' => 'midtrans',
                            'payment_id' => $request->input('transaction_id') ?? $orderId,
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Midtrans callback exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => __('Callback processing failed'), 'message' => $e->getMessage()], 500);
        }
    }

    private function createSnapToken($paymentData, $settings)
    {
        try {
            $secretKey = $settings['midtrans_secret_key']
                ?? $settings['payment_settings']['midtrans_secret_key']
                ?? env('MIDTRANS_SECRET_KEY');

            $mode = $settings['midtrans_mode']
                ?? $settings['payment_settings']['midtrans_mode']
                ?? env('MIDTRANS_MODE', 'sandbox');

            if (empty($secretKey)) {
                throw new \Exception('Midtrans secret key is missing.');
            }

            $baseUrl = $mode === 'live'
                ? 'https://api.midtrans.com'
                : 'https://api.sandbox.midtrans.com';

            Log::info('Midtrans request started', [
                'url' => $baseUrl . '/snap/v1/transactions',
                'mode' => $mode,
                'key_prefix' => substr($secretKey, 0, 8),
                'payload' => $paymentData,
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . '/snap/v1/transactions',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($paymentData),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . base64_encode($secretKey . ':'),
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \Exception('cURL Error: ' . $curlError);
            }

            if (!in_array($httpCode, [200, 201])) {
                Log::error('Midtrans API error', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                throw new \Exception("HTTP Error: {$httpCode} - {$response}");
            }

            $result = json_decode($response, true);
            if (!is_array($result)) {
                throw new \Exception('Invalid JSON response from Midtrans: ' . $response);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Midtrans createSnapToken exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}