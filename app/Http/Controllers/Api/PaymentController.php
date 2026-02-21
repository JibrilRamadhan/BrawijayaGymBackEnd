<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Plan;
use App\Models\MemberProfile;
use App\Models\NonMemberProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Transaction;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
    }

    /**
     * Midtrans Webhook Callback
     */
    public function callback(Request $request)
    {
        try {
            $notification = new Notification();

            $status = $notification->transaction_status;
            $orderId = $notification->order_id;
            $paymentType = $notification->payment_type;
            $transactionId = $notification->transaction_id;

            $payment = Payment::where('midtrans_order_id', $orderId)->first();

            if (!$payment) {
                return response()->json(['message' => 'Payment record not found'], 404);
            }

            return DB::transaction(function () use ($payment, $status, $paymentType, $transactionId) {
                if ($status == 'settlement' || $status == 'capture') {
                    // Update Payment
                    $payment->update([
                        'status' => 'settlement',
                        'payment_method' => $paymentType,
                        'midtrans_transaction_id' => $transactionId,
                        'paid_at' => now(),
                    ]);

                    // Activate Subscription
                    $this->activateSubscription($payment);
                } elseif (in_array($status, ['cancel', 'deny', 'expire'])) {
                    $payment->update(['status' => 'failed']);
                }

                return response()->json(['message' => 'Callback processed']);
            });

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * For Frontend: Check payment status and get receipt (struk)
     * Also syncs status from Midtrans API if still pending.
     */
    public function checkStatus($uuid)
    {
        $payment = Payment::with(['user', 'plan', 'subscription'])
            ->where('uuid', $uuid)->first();

        if (!$payment) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // If still pending, check directly from Midtrans API
        if ($payment->status === 'pending') {
            try {
                $midtransStatus = Transaction::status($payment->midtrans_order_id);
                $status = $midtransStatus->transaction_status ?? null;

                if ($status === 'settlement' || $status === 'capture') {
                    DB::transaction(function () use ($payment, $midtransStatus) {
                        $payment->update([
                            'status' => 'settlement',
                            'payment_method' => $midtransStatus->payment_type ?? null,
                            'midtrans_transaction_id' => $midtransStatus->transaction_id ?? null,
                            'paid_at' => now(),
                        ]);

                        $this->activateSubscription($payment);
                    });

                    // Reload updated data
                    $payment->refresh();
                    $payment->load(['user', 'plan', 'subscription']);
                } elseif (in_array($status, ['cancel', 'deny', 'expire'])) {
                    $payment->update(['status' => 'failed']);
                    $payment->refresh();
                }
            } catch (\Exception $e) {
                // Midtrans API unreachable, just return current DB status
            }
        }

        // Build receipt data
        $receipt = [
            'order_id' => $payment->midtrans_order_id,
            'amount' => $payment->amount,
            'plan_name' => $payment->plan->name ?? null,
            'plan_type' => $payment->plan->type ?? null,
            'user' => $payment->user->username ?? null,
            'email' => $payment->user->email ?? null,
            'paid_at' => $payment->paid_at,
            'status' => $payment->status,
            'method' => $payment->payment_method,
        ];

        // Add subscription details if available
        if ($payment->subscription) {
            $receipt['subscription'] = [
                'uuid' => $payment->subscription->uuid,
                'start_date' => $payment->subscription->start_date,
                'end_date' => $payment->subscription->end_date,
                'status' => $payment->subscription->status,
            ];
        }

        return response()->json([
            'status' => $payment->status,
            'receipt' => $receipt,
        ]);
    }

    private function activateSubscription($payment)
    {
        $plan = $payment->plan;
        $user = $payment->user;
        $metadata = $payment->metadata ?? [];

        // Calculate End Date
        $startDate = now();
        $endDate = $this->calculateEndDate($startDate, $plan, $payment);

        $subscription = Subscription::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
        ]);

        $payment->update(['subscription_id' => $subscription->id]);

        // Use metadata for profile data (populated during join)
        $name = $metadata['name'] ?? $user->username;
        $phone = $metadata['phone'] ?? '000';

        if (Str::lower($plan->type) === 'member') {
            // Become non-guest
            $user->update(['is_guest' => false]);

            // Save to MemberProfile using real data from metadata
            MemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'first_name' => $metadata['first_name'] ?? $name,
                    'last_name' => $metadata['last_name'] ?? null,
                    'middle_name' => $metadata['middle_name'] ?? null,
                    'jenis_klamin' => $metadata['jenis_kelamin'] ?? 'L',
                ]
            );
        } else {
            // Stay/Update NonMemberProfile
            NonMemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'phone' => $phone,
                ]
            );
        }
    }

    private function calculateEndDate($start, $plan, $payment)
    {
        $start = Carbon::parse($start);
        $metadata = $payment->metadata ?? [];

        if (Str::lower($plan->type) === 'harian') {
            // Use custom_days from metadata, or fallback to price calculation
            $days = $metadata['custom_days'] ?? ($payment->amount / max($plan->price, 1));
            return $start->addDays((int) $days);
        }

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
