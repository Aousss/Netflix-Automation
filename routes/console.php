<?php

use App\Models\AccountSetting;
use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('billing:generate-cycle {month? : Billing cycle month in YYYY-MM format}', function (?string $month = null) {
    $targetMonth = $month ?: now()->format('Y-m');
    $monthStart = Carbon::createFromFormat('Y-m', $targetMonth)->startOfMonth();
    $settings = AccountSetting::first();
    $totalCost = $settings?->total_monthly_cost ?? 55.00;
    $billingDay = max(1, min(28, (int) ($settings?->billing_day_of_month ?? 1)));

    $cycle = BillingCycle::firstOrCreate(
        ['cycle_month' => $targetMonth],
        [
            'total_cost' => $totalCost,
            'due_date' => $monthStart->copy()->day($billingDay)->addDays(5)->toDateString(),
            'notes' => "Monthly Netflix Household split-billing cycle ({$targetMonth}).",
        ]
    );

    $members = User::where('is_active', true)->get();
    $createdCount = 0;

    foreach ($members as $member) {
        $existing = Payment::where('user_id', $member->id)
            ->where('billing_cycle_id', $cycle->id)
            ->first();

        if ($existing) {
            continue;
        }

        $share = (float) $member->monthly_share_amount;
        $status = 'pending';
        $reference = null;
        $paidAt = null;
        $verifiedAt = null;

        if ($member->isAdmin()) {
            $status = 'paid';
            $reference = 'Subscription Leader Share';
            $paidAt = now();
            $verifiedAt = now();
        } elseif ((float) $member->credit_balance >= $share && $share > 0) {
            $member->decrement('credit_balance', $share);
            $status = 'paid';
            $reference = 'Auto-deducted from Advance Credit Balance';
            $paidAt = now();
            $verifiedAt = now();
        }

        Payment::create([
            'user_id' => $member->id,
            'billing_cycle_id' => $cycle->id,
            'amount' => $share,
            'status' => $status,
            'reference_note' => $reference,
            'paid_at' => $paidAt,
            'verified_at' => $verifiedAt,
        ]);

        $createdCount++;
    }

    AuditLog::record('billing.cycle_generated', [
        'cycle_month' => $targetMonth,
        'payments_initialized' => $createdCount,
    ]);

    $this->info("Billing cycle [{$targetMonth}] ready ({$createdCount} member payment records created).");
})->purpose('Generate monthly Netflix split-billing cycle and initialize member payment ledger');

Schedule::command('billing:generate-cycle')->monthlyOn(1, '00:05');
