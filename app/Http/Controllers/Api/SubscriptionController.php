<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\NonMemberProfile;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Midtrans\Config;
use Midtrans\Snap;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');

        if (config('app.env') === 'local') {
            Config::$curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            if (!isset(Config::$curlOptions[CURLOPT_HTTPHEADER])) {
                Config::$curlOptions[CURLOPT_HTTPHEADER] = [];
            }
        }
    }

    /**
     * Get available plans.
     */
    public function getPlans()
    {
        $plans = Plan::where('is_active', true)->get();
        return response()->json(['data' => $plans]);
    }

    /**
     * Join/Subscribe to a plan.
     */
    public function join(Request $request)
    {
        $rules = [
            'plan_id' => 'required|exists:plans,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'days' => 'nullable|integer|min:1',
        ];

        $plan = Plan::find($request->plan_id);

        // Extra validation for member-type plans
        if ($plan && Str::lower($plan->type) === 'member') {
            $rules['jenis_kelamin'] = 'required|in:L,P';
            $rules['alamat'] = 'required|string|max:500';
        }

        $request->validate($rules);

        $user = auth()->user();

        // Check if user already has a trial if they are joining a trial plan
        if (Str::lower($plan->type) === 'trial') {
            // Check globally by email — prevent repeat trial abuse
            $emailUsedTrial = User::where('email', $user->email)
                ->whereHas('subscriptions', function ($query) {
                    $query->whereHas('plan', function ($q) {
                        $q->where('type', 'trial');
                    });
                })->exists();

            if ($emailUsedTrial) {
                return response()->json(['message' => 'Email ini sudah pernah menggunakan trial.'], 403);
            }

            return $this->handleTrialRegistration($user, $plan, $request);
        }

        // Handle Paid Plans (Harian or Member)
        return $this->generatePayment($user, $plan, $request);
    }

    /**
     * Logic for free trial registration.
     */
    private function handleTrialRegistration($user, $plan, $request)
    {
        return DB::transaction(function () use ($user, $plan, $request) {
            NonMemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $request->name,
                    'phone' => $request->phone,
                ]
            );

            $startDate = now();
            $endDate = $startDate->copy()->addHours(24);

            $subscription = Subscription::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
            ]);

            return response()->json([
                'message' => 'Trial activated successfully.',
                'data' => [
                    'subscription_uuid' => $subscription->uuid,
                    'plan_name' => $plan->name,
                    'type' => $plan->type,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                ]
            ], 201);
        });
    }

    /**
     * Generate Midtrans Payment for paid plans.
     */
    private function generatePayment($user, $plan, $request)
    {
        $totalDays = (Str::lower($plan->type) === 'harian' && $request->has('days')) ? $request->days : $plan->duration_value;
        if ($plan->duration_unit === 'months')
            $totalDays *= 30; // approximation for display

        $totalPrice = (Str::lower($plan->type) === 'harian' && $request->has('days'))
            ? $plan->price * $request->days
            : $plan->price;

        $orderId = 'GYM-' . time() . '-' . $user->id;

        // Build metadata for member profiles (used by webhook on settlement)
        $metadata = [
            'name' => $request->name,
            'phone' => $request->phone,
        ];

        if (Str::lower($plan->type) === 'member') {
            $metadata['jenis_kelamin'] = $request->jenis_kelamin;
            $metadata['alamat'] = $request->alamat;
        }

        if (Str::lower($plan->type) === 'harian' && $request->has('days')) {
            $metadata['custom_days'] = (int) $request->days;
        }

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $totalPrice,
            'midtrans_order_id' => $orderId,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        // Pre-save profile info
        NonMemberProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $request->name,
                'phone' => $request->phone,
            ]
        );

        // Midtrans Snap params
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $totalPrice,
            ],
            'customer_details' => [
                'first_name' => $request->name,
                'email' => $user->email,
                'phone' => $request->phone,
            ],
            'item_details' => [
                [
                    'id' => $plan->id,
                    'price' => (int) $plan->price,
                    'quantity' => (Str::lower($plan->type) === 'harian' && $request->has('days')) ? (int) $request->days : 1,
                    'name' => $plan->name,
                ]
            ]
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            $midtransBaseUrl = config('midtrans.is_production')
                ? 'https://app.midtrans.com/snap/v2/vtweb/'
                : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/';

            return response()->json([
                'message' => 'Payment generated. Please complete payment.',
                'data' => [
                    'snap_token' => $snapToken,
                    'redirect_url' => $midtransBaseUrl . $snapToken,
                    'payment_uuid' => $payment->uuid,
                    'order_id' => $orderId,
                    'amount' => $totalPrice,
                    'plan_name' => $plan->name,
                    'plan_type' => $plan->type,
                    'custom_days' => (Str::lower($plan->type) === 'harian') ? $request->days : null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Midtrans Error: ' . $e->getMessage()], 500);
        }
    }

    private function calculateEndDate($start, $plan)
    {
        $start = Carbon::parse($start);
        $value = $plan->duration_value;
        switch (Str::lower($plan->duration_unit)) {
            case 'days':
                return $start->addDays($value);
            case 'months':
                return $start->addMonths($value);
            case 'years':
                return $start->addYears($value);
            default:
                return $start->addDays($value);
        }
    }
}
