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

        return response()->json([
            'total_users' => $totalUsers,
            'total_revenue' => $totalRevenue,
            'recent_payments' => $recentPayments,
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
}
