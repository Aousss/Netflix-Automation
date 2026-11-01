<?php

namespace App\Http\Controllers;

use App\Models\AccountSetting;
use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\Payment;
use App\Models\User;
use App\Services\NetflixImapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $settings = AccountSetting::first() ?? new AccountSetting([
            'netflix_email' => 'household.leader@gmail.com',
            'plan_name' => 'Netflix Premium 4K',
            'total_monthly_cost' => 55.00,
            'billing_day_of_month' => 1,
        ]);

        $cycles = BillingCycle::with(['payments.user', 'payments.verifier'])
            ->orderByDesc('cycle_month')
            ->limit(6)
            ->get();

        $currentCycle = $cycles->first();
        $historicalCycles = $cycles->slice(1)->values();

        $myPayment = $currentCycle
            ? $currentCycle->payments->firstWhere('user_id', $user->id)
            : null;

        $members = User::orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        $pendingReceipts = Payment::with(['user', 'billingCycle'])
            ->where('status', 'verifying')
            ->orderByDesc('paid_at')
            ->get();

        $recentLogs = $user->isAdmin()
            ? AuditLog::with('user')->orderByDesc('id')->limit(15)->get()
            : collect();

        $verifiedUntil = $request->session()->get('vault_verified_until');
        $isStepUpVerified = $verifiedUntil && now()->lt($verifiedUntil);

        return view('dashboard', compact(
            'user',
            'settings',
            'currentCycle',
            'historicalCycles',
            'myPayment',
            'members',
            'pendingReceipts',
            'recentLogs',
            'isStepUpVerified'
        ));
    }

    public function fetchImapCodes(NetflixImapService $imapService): JsonResponse
    {
        return response()->json($imapService->fetchRecentCodes());
    }
}

