<?php

namespace Database\Seeders;

use App\Models\AccountSetting;
use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        AccountSetting::updateOrCreate(
            ['id' => 1],
            [
                'netflix_email' => 'household.leader@gmail.com',
                'netflix_password' => 'Nflx#Ultra4K!2026',
                'password_updated_at' => now()->subDays(3),
                'plan_name' => 'Netflix Premium 4K (UHD)',
                'total_monthly_cost' => 55.00,
                'billing_day_of_month' => 1,
                'bank_name' => 'Maybank (Malayan Banking Berhad)',
                'bank_account_number' => '1642 8940 3192',
                'bank_account_holder' => 'Firdaus (Household Admin)',
                'duitnow_qr_path' => null,
            ]
        );

        $members = [
            [
                'name' => env('ADMIN_NAME', 'Firdaus (Leader)'),
                'email' => strtolower(trim((string) env('ADMIN_EMAIL', 'admin@household.local'))),
                'role' => 'admin',
                'netflix_profile_name' => 'Firdaus',
                'netflix_profile_pin' => '1024',
                'monthly_share_amount' => 11.00,
                'credit_balance' => 0.00,
                'status' => 'paid',
            ],
            [
                'name' => 'Alex Tan',
                'email' => 'alex@household.local',
                'role' => 'member',
                'netflix_profile_name' => 'Alex',
                'netflix_profile_pin' => '4829',
                'monthly_share_amount' => 11.00,
                'credit_balance' => 22.00,
                'status' => 'paid',
            ],
            [
                'name' => 'Sarah Lim',
                'email' => 'sarah@household.local',
                'role' => 'member',
                'netflix_profile_name' => 'Sarah',
                'netflix_profile_pin' => '7391',
                'monthly_share_amount' => 11.00,
                'credit_balance' => 0.00,
                'status' => 'verifying',
            ],
            [
                'name' => 'Amirul Hakim',
                'email' => 'amirul@household.local',
                'role' => 'member',
                'netflix_profile_name' => 'Amirul',
                'netflix_profile_pin' => '5510',
                'monthly_share_amount' => 11.00,
                'credit_balance' => 0.00,
                'status' => 'pending',
            ],
            [
                'name' => 'Chloe Wong',
                'email' => 'chloe@household.local',
                'role' => 'member',
                'netflix_profile_name' => 'Chloe',
                'netflix_profile_pin' => '9082',
                'monthly_share_amount' => 11.00,
                'credit_balance' => 0.00,
                'status' => 'overdue',
            ],
        ];

        $userModels = [];
        foreach ($members as $data) {
            $status = $data['status'];
            unset($data['status']);

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['is_active' => true])
            );
            $userModels[] = ['user' => $user, 'status' => $status];
        }

        // Seed 5 previous months (all paid) + current month
        for ($i = 5; $i >= 1; $i--) {
            $monthDate = now()->subMonths($i)->startOfMonth();
            $cycle = BillingCycle::updateOrCreate(
                ['cycle_month' => $monthDate->format('Y-m')],
                [
                    'total_cost' => 55.00,
                    'due_date' => $monthDate->copy()->addDays(4)->toDateString(),
                    'notes' => 'Settled monthly Netflix Premium 4K cycle.',
                ]
            );

            foreach ($userModels as $entry) {
                Payment::updateOrCreate(
                    [
                        'user_id' => $entry['user']->id,
                        'billing_cycle_id' => $cycle->id,
                    ],
                    [
                        'amount' => $entry['user']->monthly_share_amount,
                        'status' => 'paid',
                        'reference_note' => 'DuitNow Instant Transfer',
                        'paid_at' => $monthDate->copy()->addDays(2),
                        'verified_at' => $monthDate->copy()->addDays(2),
                        'verified_by' => $userModels[0]['user']->id,
                    ]
                );
            }
        }

        $currentMonth = now()->startOfMonth();
        $currentCycle = BillingCycle::updateOrCreate(
            ['cycle_month' => $currentMonth->format('Y-m')],
            [
                'total_cost' => 55.00,
                'due_date' => $currentMonth->copy()->addDays(6)->toDateString(),
                'notes' => 'Current active Netflix Household billing cycle.',
            ]
        );

        foreach ($userModels as $entry) {
            $user = $entry['user'];
            $status = $entry['status'];

            Payment::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'billing_cycle_id' => $currentCycle->id,
                ],
                [
                    'amount' => $user->monthly_share_amount,
                    'status' => $status,
                    'reference_note' => match ($status) {
                        'paid' => 'DuitNow QR #DN' . rand(100000, 999999),
                        'verifying' => 'Maybank2u Ref #M2U884120',
                        default => null,
                    },
                    'receipt_path' => $status === 'verifying' ? 'demo-receipt' : null,
                    'paid_at' => in_array($status, ['paid', 'verifying']) ? now()->subHours(6) : null,
                    'verified_at' => $status === 'paid' ? now()->subHours(5) : null,
                    'verified_by' => $status === 'paid' ? $userModels[0]['user']->id : null,
                ]
            );
        }

        AuditLog::record('system.seeded', ['cycle' => $currentCycle->cycle_month], $userModels[0]['user']->id);
    }
}
