<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function showLogin(): View
    {
        $whitelistedUsers = collect();
        $dbError = config('app.db_boot_error');

        if (! $dbError) {
            try {
                if (User::count() === 0) {
                    (new \Database\Seeders\DatabaseSeeder())->run();
                }

                $whitelistedUsers = User::where('is_active', true)
                    ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
                    ->orderBy('name')
                    ->get();
            } catch (Throwable $e) {
                $dbError = $e->getMessage();
            }
        }

        $firebaseConfig = config('services.firebase');
        $hasFirebase = ! empty($firebaseConfig['api_key']) && ! empty($firebaseConfig['project_id']);

        return view('auth.login', compact('whitelistedUsers', 'firebaseConfig', 'hasFirebase', 'dbError'));
    }

    public function handleFirebaseCallback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $apiKey = (string) config('services.firebase.api_key');
        if ($apiKey === '') {
            return redirect()->route('login')->with('error', 'Firebase API key is not configured in .env.');
        }

        try {
            $response = Http::timeout(10)->post(
                'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey),
                ['idToken' => $validated['id_token']]
            );
        } catch (Throwable $e) {
            AuditLog::record('auth.google_error', ['provider' => 'firebase', 'message' => $e->getMessage()]);

            return redirect()->route('login')->with('error', 'Unable to verify Firebase Google token. Please check your internet connection or use a Magic Link.');
        }

        $fbUser = $response->json('users.0');
        if (! $response->successful() || empty($fbUser['email'])) {
            AuditLog::record('auth.google_error', [
                'provider' => 'firebase',
                'status' => $response->status(),
            ]);

            return redirect()->route('login')->with('error', 'Firebase Google authentication failed or token expired. Please try again.');
        }

        $email = strtolower(trim((string) $fbUser['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user || ! $user->is_active) {
            AuditLog::record('auth.login_failed', [
                'email' => $email,
                'provider' => 'firebase_google',
                'reason' => 'not_whitelisted_or_inactive',
            ]);

            return redirect()->route('login')->with(
                'error',
                "Access Denied (403): Your Google email ({$email}) is not in the 5-member household whitelist yet. Sign in as Admin and update a member's email to {$email} in Admin → Members."
            );
        }

        $user->update([
            'google_id' => $fbUser['localId'] ?? $user->google_id,
            'avatar' => $fbUser['photoUrl'] ?? $user->avatar,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        AuditLog::record('auth.google_login', [
            'email' => $user->email,
            'provider' => 'firebase',
        ], $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function redirectToGoogle(): RedirectResponse
    {
        if (empty(config('services.google.client_id')) || empty(config('services.google.client_secret'))) {
            return redirect()->route('login')->with('error', 'Google OAuth credentials are not configured in .env yet. Use the Signed Magic Link or Demo Household Switcher below.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            AuditLog::record('auth.google_error', ['message' => $e->getMessage()]);

            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again or use a Magic Link.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user || ! $user->is_active) {
            AuditLog::record('auth.login_failed', [
                'email' => $email,
                'reason' => 'not_whitelisted_or_inactive',
            ]);

            abort(403, 'Access Denied: Your Google email (' . e($email) . ') is not on the 5-member Netflix Household whitelist.');
        }

        $user->update([
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar() ?: $user->avatar,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        AuditLog::record('auth.google_login', ['email' => $user->email], $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function sendMagicLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user || ! $user->is_active) {
            AuditLog::record('auth.login_failed', [
                'email' => $email,
                'method' => 'magic_link',
            ]);

            return back()->with('error', '403 Forbidden: This email address is not in the active 5-member household whitelist.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'auth.magic.verify',
            now()->addMinutes(15),
            ['user' => $user->id]
        );

        try {
            Mail::raw(
                "Hi {$user->name},\n\nClick the secure link below to sign into the Netflix Household Hub (valid for 15 minutes):\n{$signedUrl}\n\nIf you did not request this link, you can safely ignore this email.",
                function ($message) use ($user) {
                    $message->to($user->email, $user->name)
                        ->subject('Your Netflix Household Hub Magic Sign-In Link');
                }
            );
        } catch (Throwable $e) {
            // Logged gracefully when local SMTP is not running
        }

        AuditLog::record('auth.magic_link_sent', ['email' => $user->email], $user->id);

        $flash = back()->with('status', "A 15-minute signed magic link has been sent to {$user->email}.");
        if (config('app.debug') || config('mail.default') === 'log') {
            $flash->with('magic_link_preview', $signedUrl);
        }

        return $flash;
    }

    public function verifyMagicLink(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This Magic Link has expired or has an invalid signature.');
        }

        if (! $user->is_active) {
            abort(403, 'Your household membership is currently inactive.');
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        AuditLog::record('auth.magic_link_verified', ['email' => $user->email], $user->id);

        return redirect()->route('dashboard')->with('status', "Welcome back, {$user->name}!");
    }

    public function demoSwitch(Request $request, User $user): RedirectResponse
    {
        $hasDefaultAdmin = User::where('role', 'admin')
            ->where('email', 'admin@household.local')
            ->exists();

        if (! app()->environment('local') && ! config('app.debug') && ! $hasDefaultAdmin) {
            abort(403);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('vault_verified_until');

        AuditLog::record('auth.demo_switch', ['switched_to' => $user->email], $user->id);

        return redirect()->route('dashboard')->with('status', "Switched active session to {$user->name} ({$user->role}).");
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditLog::record('auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

