<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Payment;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function getStats()
    {
        $totalUsers = User::where('is_guest', false)->count();
        $totalRevenue = Payment::where('status', 'settlement')->sum('amount');

        $recentPayments = Payment::with(['user:id,email,name', 'plan:id,name'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Generate chart data for the last 7 days
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            $dailyRevenue = Payment::where('status', 'settlement')
                ->whereDate('created_at', $date)
                ->sum('amount');

            $dailyUsers = User::where('is_guest', false)
                ->whereDate('created_at', $date)
                ->count();

            $chartData[] = [
                'date' => $date->format('M d'),
                'revenue' => $dailyRevenue,
                'new_users' => $dailyUsers
            ];
        }

        return response()->json([
            'total_users' => $totalUsers,
            'total_revenue' => $totalRevenue,
            'recent_payments' => $recentPayments,
            'chart_data' => $chartData
        ]);
    }

    public function getUsers()
    {
        $users = User::with('roles:id,name')
            ->with(['memberProfile', 'nonMemberProfile'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    public function getPayments()
    {
        $payments = Payment::with(['user:id,email,name', 'plan:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }

    public function toggleUserStatus($id)
    {
        $user = User::findOrFail($id);

        // Don't allow admins to deactivate themselves
        if (auth()->id() == $user->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        if (!$user->is_active) {
            // Revoke all tokens to immediately kick the user out of the app
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'User status updated successfully.',
            'user' => $user
        ]);
    }
}
