<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PaymentController extends Controller
{
    public function uploadReceipt(Request $request, Payment $payment): RedirectResponse
    {
        $user = $request->user();

        if ($payment->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'You can only upload receipts for your own monthly share.');
        }

        $validated = $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'reference_note' => ['nullable', 'string', 'max:120'],
        ]);

        $disk = config('filesystems.default', 'local');
        $path = $request->file('receipt')->store('receipts', $disk);

        $payment->update([
            'receipt_path' => $path,
            'reference_note' => $validated['reference_note'] ?: 'DuitNow Transfer Receipt',
            'status' => 'verifying',
            'paid_at' => now(),
        ]);

        AuditLog::record('payment.receipt_uploaded', [
            'payment_id' => $payment->id,
            'cycle' => $payment->billingCycle?->cycle_month,
            'amount' => $payment->amount,
        ]);

        $admins = User::where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            try {
                Mail::raw(
                    "{$user->name} uploaded a payment receipt (RM {$payment->amount}) for cycle {$payment->billingCycle?->cycle_month}. Please review and approve it in the Admin Panel.",
                    function ($message) use ($admin, $user) {
                        $message->to($admin->email, $admin->name)
                            ->subject("Payment Receipt Uploaded by {$user->name}");
                    }
                );
            } catch (Throwable $e) {
                // Ignore mail errors in local dev
            }
        }

        return back()->with('status', 'Transfer receipt uploaded! Status is now Pending Verification.');
    }

    public function showReceipt(Request $request, Payment $payment): Response
    {
        $user = $request->user();
        if ($payment->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403);
        }

        if ($payment->receipt_path === 'demo-receipt') {
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="480" height="620" viewBox="0 0 480 620">
  <rect width="480" height="620" rx="20" fill="#18181b"/>
  <rect x="24" y="24" width="432" height="572" rx="14" fill="#27272a" stroke="#3f3f46"/>
  <circle cx="240" cy="95" r="32" fill="#10b981" fill-opacity="0.2"/>
  <path d="M226 95l10 10 20-22" stroke="#10b981" stroke-width="4" fill="none" stroke-linecap="round"/>
  <text x="240" y="155" text-anchor="middle" fill="#f4f4f5" font-family="sans-serif" font-size="20" font-weight="bold">DuitNow Instant Transfer</text>
  <text x="240" y="182" text-anchor="middle" fill="#a1a1aa" font-family="sans-serif" font-size="13">Successful Transaction Receipt</text>
  <text x="240" y="235" text-anchor="middle" fill="#10b981" font-family="sans-serif" font-size="34" font-weight="bold">RM {$payment->amount}</text>
  <line x1="56" y1="270" x2="424" y2="270" stroke="#3f3f46" stroke-dasharray="4 4"/>
  <text x="64" y="315" fill="#a1a1aa" font-family="sans-serif" font-size="13">Sender</text>
  <text x="416" y="315" text-anchor="end" fill="#f4f4f5" font-family="sans-serif" font-size="14" font-weight="bold">{$payment->user->name}</text>
  <text x="64" y="355" fill="#a1a1aa" font-family="sans-serif" font-size="13">Recipient Bank</text>
  <text x="416" y="355" text-anchor="end" fill="#f4f4f5" font-family="sans-serif" font-size="14">Maybank (1642 8940 3192)</text>
  <text x="64" y="395" fill="#a1a1aa" font-family="sans-serif" font-size="13">Reference</text>
  <text x="416" y="395" text-anchor="end" fill="#f4f4f5" font-family="sans-serif" font-size="14">{$payment->reference_note}</text>
  <text x="64" y="435" fill="#a1a1aa" font-family="sans-serif" font-size="13">Billing Cycle</text>
  <text x="416" y="435" text-anchor="end" fill="#f4f4f5" font-family="sans-serif" font-size="14">{$payment->billingCycle->cycle_month}</text>
  <text x="64" y="475" fill="#a1a1aa" font-family="sans-serif" font-size="13">Timestamp</text>
  <text x="416" y="475" text-anchor="end" fill="#f4f4f5" font-family="sans-serif" font-size="14">{$payment->paid_at?->format('d M Y, h:i A')}</text>
</svg>
SVG;

            return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
        }

        $disk = config('filesystems.default', 'local');
        if (! $payment->receipt_path || ! Storage::disk($disk)->exists($payment->receipt_path)) {
            abort(404, 'Receipt file not found.');
        }

        return Storage::disk($disk)->response($payment->receipt_path);
    }
}

