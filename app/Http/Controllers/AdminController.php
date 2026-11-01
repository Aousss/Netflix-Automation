<?php

namespace App\Http\Controllers;

use App\Models\AccountSetting;
use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AdminController extends Controller
{
    protected function authorizeAdmin(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Admin privileges required.');
        }
    }

    public function verifyPayment(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $action = $request->input('decision', 'approve');

        if ($action === 'reject') {
            $payment->update([
                'status' => 'overdue',
                'verified_at' => null,
                'verified_by' => null,
            ]);

            AuditLog::record('admin.payment_rejected', [
                'payment_id' => $payment->id,
                'member' => $payment->user?->name,
            ]);

            return back()
                ->with('status', "Marked {$payment->user?->name}'s payment as Overdue (receipt rejected).")
                ->with('admin_tab', 'queue');
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => $payment->paid_at ?? now(),
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        AuditLog::record('admin.payment_verified', [
            'payment_id' => $payment->id,
            'member' => $payment->user?->name,
            'amount' => $payment->amount,
        ]);

        return back()
            ->with('status', "Approved {$payment->user?->name}'s RM {$payment->amount} payment! Vault access unlocked.")
            ->with('admin_tab', 'queue');
    }

    public function updatePaymentStatus(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,verifying,paid,overdue'],
        ]);

        $payment->update([
            'status' => $validated['status'],
            'paid_at' => $validated['status'] === 'paid' ? ($payment->paid_at ?? now()) : $payment->paid_at,
            'verified_at' => $validated['status'] === 'paid' ? now() : null,
            'verified_by' => $validated['status'] === 'paid' ? $request->user()->id : null,
        ]);

        AuditLog::record('admin.payment_status_updated', [
            'payment_id' => $payment->id,
            'status' => $validated['status'],
        ]);

        return back()
            ->with('status', "Updated {$payment->user?->name}'s payment status to " . strtoupper($validated['status']) . '.')
            ->with('admin_tab', 'queue');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'netflix_email' => ['required', 'email', 'max:190'],
            'netflix_password' => ['nullable', 'string', 'min:4', 'max:190'],
            'plan_name' => ['required', 'string', 'max:100'],
            'total_monthly_cost' => ['required', 'numeric', 'min:1'],
            'billing_day_of_month' => ['required', 'integer', 'min:1', 'max:28'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:60'],
            'bank_account_holder' => ['nullable', 'string', 'max:120'],
            'duitnow_qr' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:4096'],
        ]);

        $settings = AccountSetting::current();

        $updates = [
            'netflix_email' => $validated['netflix_email'],
            'plan_name' => $validated['plan_name'],
            'total_monthly_cost' => $validated['total_monthly_cost'],
            'billing_day_of_month' => $validated['billing_day_of_month'],
            'bank_name' => $validated['bank_name'],
            'bank_account_number' => $validated['bank_account_number'],
            'bank_account_holder' => $validated['bank_account_holder'],
        ];

        if (! empty($validated['netflix_password'])) {
            $updates['netflix_password'] = $validated['netflix_password'];
            $updates['password_updated_at'] = now();
        }

        if ($request->hasFile('duitnow_qr')) {
            $updates['duitnow_qr_path'] = $request->file('duitnow_qr')->store('duitnow', 'public');
        }

        $settings->update($updates);

        AuditLog::record('admin.settings_updated', [
            'password_rotated' => ! empty($validated['netflix_password']),
        ]);

        return back()
            ->with('status', 'Master Netflix Vault & DuitNow Billing settings updated.')
            ->with('admin_tab', 'vault');
    }

    public function updateMember(Request $request, User $member): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,' . $member->id],
            'netflix_profile_name' => ['required', 'string', 'max:60'],
            'netflix_profile_pin' => ['nullable', 'digits:4'],
            'monthly_share_amount' => ['required', 'numeric', 'min:0'],
            'credit_balance' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updates = [
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'netflix_profile_name' => $validated['netflix_profile_name'],
            'monthly_share_amount' => $validated['monthly_share_amount'],
            'credit_balance' => $validated['credit_balance'],
            'is_active' => $request->boolean('is_active', true),
        ];

        if (! empty($validated['netflix_profile_pin'])) {
            $updates['netflix_profile_pin'] = $validated['netflix_profile_pin'];
        }

        $member->update($updates);

        AuditLog::record('admin.member_updated', ['member_id' => $member->id, 'email' => $member->email]);

        return back()
            ->with('status', "Updated household member {$member->name}.")
            ->with('admin_tab', 'members');
    }

    public function generateCycle(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'cycle_month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $month = $validated['cycle_month'] ?? now()->format('Y-m');
        Artisan::call('billing:generate-cycle', ['month' => $month]);

        return back()
            ->with('status', "Generated/synced billing cycle for {$month}.")
            ->with('admin_tab', 'members');
    }
}

