<?php

namespace App\Http\Controllers;

use App\Models\AccountSetting;
use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class VaultController extends Controller
{
    public function requestOtp(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentPayment = $this->currentPaymentFor($user->id);

        if (! $user->canAccessVault($currentPayment)) {
            AuditLog::record('vault.blocked_overdue', ['payment_id' => $currentPayment?->id]);

            return response()->json([
                'message' => 'Payment Required: Your current monthly share is marked as Overdue. Please upload your DuitNow transfer receipt or activate your one-time 24-hour emergency grace bypass.',
                'requires_payment' => true,
                'can_use_grace_bypass' => $user->grace_bypass_used_at === null,
            ], 402);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put("vault_otp_{$user->id}", $code, now()->addMinutes(5));

        try {
            Mail::raw(
                "Your Netflix Household Vault 6-digit verification code is: {$code}\n\nThis code expires in 5 minutes.",
                function ($message) use ($user) {
                    $message->to($user->email, $user->name)
                        ->subject("[$code] Netflix Vault Step-Up Verification Code");
                }
            );
        } catch (Throwable $e) {
            // Continue if local mail is set to log
        }

        AuditLog::record('vault.otp_requested', ['email' => $user->email]);

        $payload = [
            'message' => "A 6-digit OTP has been sent to {$user->email} (valid for 5 minutes).",
            'expires_in_seconds' => 300,
        ];

        if (config('app.debug') || config('mail.default') === 'log') {
            $payload['demo_otp'] = $code;
        }

        return response()->json($payload);
    }

    public function reveal(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentPayment = $this->currentPaymentFor($user->id);

        if (! $user->canAccessVault($currentPayment)) {
            return response()->json([
                'message' => 'Payment Required: Please upload your monthly transfer receipt before revealing the master password.',
                'requires_payment' => true,
            ], 402);
        }

        $verifiedUntil = $request->session()->get('vault_verified_until');
        $alreadyStepUpVerified = $verifiedUntil && now()->lt($verifiedUntil);

        if (! $alreadyStepUpVerified) {
            $validated = $request->validate([
                'otp' => ['required', 'digits:6'],
            ]);

            $cachedOtp = Cache::get("vault_otp_{$user->id}");
            if (! $cachedOtp || ! hash_equals((string) $cachedOtp, (string) $validated['otp'])) {
                AuditLog::record('vault.otp_failed');

                return response()->json([
                    'message' => 'Invalid or expired 6-digit OTP code. Please request a new code.',
                ], 422);
            }

            Cache::forget("vault_otp_{$user->id}");
            $request->session()->put('vault_verified_until', now()->addMinutes(15));
        }

        $settings = AccountSetting::current();

        AuditLog::record('vault.password_revealed', [
            'profile' => $user->netflix_profile_name,
            'grace_bypass' => $user->hasActiveGraceBypass(),
        ]);

        return response()->json([
            'email' => $settings->netflix_email,
            'password' => $settings->netflix_password,
            'profile_name' => $user->netflix_profile_name,
            'profile_pin' => $user->netflix_profile_pin,
            'updated_human' => $settings->password_updated_at?->diffForHumans() ?? 'Recently',
            'remask_after_seconds' => 15,
        ]);
    }

    public function activateGraceBypass(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->grace_bypass_used_at !== null && $user->grace_bypass_used_at->gt(now()->subDays(30))) {
            return response()->json([
                'message' => 'Emergency 24-hour grace bypass has already been used this billing cycle.',
            ], 422);
        }

        $user->update([
            'grace_bypass_used_at' => now(),
        ]);

        AuditLog::record('vault.grace_bypass_activated', [
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ]);

        return response()->json([
            'message' => '24-hour emergency grace bypass activated. You may now request a Vault OTP to reveal the master password.',
            'grace_expires_at' => now()->addHours(24)->diffForHumans(),
        ]);
    }

    protected function currentPaymentFor(int $userId): ?Payment
    {
        $latestCycle = BillingCycle::orderByDesc('cycle_month')->first();
        if (! $latestCycle) {
            return null;
        }

        return Payment::where('billing_cycle_id', $latestCycle->id)
            ->where('user_id', $userId)
            ->first();
    }
}

