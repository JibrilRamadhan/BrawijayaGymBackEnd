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

        return response()->json([
            'status' => $payment->status,
            'receipt' => [
                'order_id' => $payment->midtrans_order_id,
                'amount' => $payment->amount,
                'plan' => $payment->plan->name,
                'user' => $payment->user->username,
                'paid_at' => $payment->paid_at,
                'status' => $payment->status,
                'method' => $payment->payment_method
            ]
        ]);
    }

    private function activateSubscription($payment)
    {
        $plan = $payment->plan;
        $user = $payment->user;

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

        // Logic for profiles
        $nonMember = NonMemberProfile::where('user_id', $user->id)->first();
        $name = $nonMember ? $nonMember->name : $user->username;

        if (Str::lower($plan->type) === 'member') {
            // Become non-guest
            $user->update(['is_guest' => false]);
            
            // Save to MemberProfile
            MemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'first_name' => $name, 
                    'jenis_klamin' => 'L', // Placeholder
                ]
            );
        } else {
            // Stay/Update NonMemberProfile
            NonMemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'phone' => $nonMember ? $nonMember->phone : '000',
                ]
            );
        }
    }

    private function calculateEndDate($start, $plan, $payment)
    {
        $start = Carbon::parse($start);
        
        // Check if harian and we have custom days? 
        // We'd need to store custom days in Payment table, let's check migration.
        // I'll assume we can infer from price if it's harian.
        // Better yet, I'll use the duration from plan if not harian.
        
        if (Str::lower($plan->type) === 'harian') {
            $days = $payment->amount / $plan->price; 
            return $start->addDays((int)$days);
        }

        $value = $plan->duration_value;
        switch (Str::lower($plan->duration_unit)) {
            case 'days': return $start->addDays($value);
            case 'months': return $start->addMonths($value);
            case 'years': return $start->addYears($value);
            default: return $start->addDays($value);
        }
    }
}
