<?php

namespace Tests\Feature;

use App\Models\AccountSetting;
use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\Payment;
use App\Models\User;
use App\Services\NetflixImapService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HouseholdPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_5_members_vault_and_billing_ledger(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEquals(5, User::count());
        $this->assertEquals(1, User::where('role', 'admin')->count());
        $this->assertEquals(4, User::where('role', 'member')->count());

        $settings = AccountSetting::current();
        $this->assertEquals('household.leader@gmail.com', $settings->netflix_email);
        $this->assertEquals('Nflx#Ultra4K!2026', $settings->netflix_password);

        $currentCycle = BillingCycle::orderByDesc('cycle_month')->first();
        $this->assertNotNull($currentCycle);
        $this->assertEquals(5, $currentCycle->payments()->count());
    }

    public function test_imap_parser_extracts_nftoken_url_4_digit_code_and_device(): void
    {
        $service = new NetflixImapService();
        $html = '<html><body>
            <p>Requested by Alex on Device: Samsung Smart TV. Location: MY.</p>
            <p>Enter code: <strong>4912</strong></p>
            <a href="https://www.netflix.com/account/travel/verify?nftoken=abc123xyz&amp;messageGuid=99">Approve</a>
        </body></html>';

        $dto = $service->parseHtmlBody($html, 'Your Netflix temporary access code', time() - 60);

        $this->assertEquals('Alex', $dto['profile_name']);
        $this->assertEquals('Samsung Smart TV', $dto['device_type']);
        $this->assertEquals('4912', $dto['code']);
        $this->assertEquals('https://www.netflix.com/account/travel/verify?nftoken=abc123xyz&messageGuid=99', $dto['approval_url']);
        $this->assertGreaterThan(800, $dto['remaining_seconds']);
    }

    public function test_vault_payment_gating_blocks_overdue_member_until_grace_bypass_or_paid(): void
    {
        $this->seed(DatabaseSeeder::class);

        $overdueUser = User::where('email', 'chloe@household.local')->firstOrFail();

        $this->actingAs($overdueUser)
            ->postJson(route('vault.request-otp'))
            ->assertStatus(402)
            ->assertJson(['requires_payment' => true]);

        // Activate 24-hour emergency grace bypass
        $this->actingAs($overdueUser)
            ->postJson(route('vault.grace-bypass'))
            ->assertOk();

        // Now OTP request succeeds
        $this->actingAs($overdueUser)
            ->postJson(route('vault.request-otp'))
            ->assertOk();
    }

    public function test_paid_member_can_verify_otp_and_reveal_encrypted_master_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $paidMember = User::where('email', 'alex@household.local')->firstOrFail();

        $this->actingAs($paidMember)
            ->postJson(route('vault.request-otp'))
            ->assertOk();

        $otp = Cache::get("vault_otp_{$paidMember->id}");
        $this->assertNotNull($otp);

        $this->actingAs($paidMember)
            ->postJson(route('vault.reveal'), ['otp' => $otp])
            ->assertOk()
            ->assertJson([
                'email' => 'household.leader@gmail.com',
                'password' => 'Nflx#Ultra4K!2026',
                'profile_name' => 'Alex',
                'profile_pin' => '4829',
            ]);

        $this->assertTrue(
            AuditLog::where('user_id', $paidMember->id)
                ->where('action', 'vault.password_revealed')
                ->exists()
        );
    }

    public function test_member_receipt_upload_and_admin_approval_workflow(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $pendingMember = User::where('email', 'amirul@household.local')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $cycle = BillingCycle::orderByDesc('cycle_month')->firstOrFail();
        $payment = Payment::where('user_id', $pendingMember->id)
            ->where('billing_cycle_id', $cycle->id)
            ->firstOrFail();

        $file = UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf');

        $this->actingAs($pendingMember)
            ->post(route('payments.upload-receipt', $payment), [
                'receipt' => $file,
                'reference_note' => 'DuitNow #DN778899',
            ])
            ->assertRedirect();

        $payment->refresh();
        $this->assertEquals('verifying', $payment->status);

        $this->actingAs($admin)
            ->post(route('admin.payments.verify', $payment), ['decision' => 'approve'])
            ->assertRedirect();

        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals($admin->id, $payment->verified_by);
    }

    public function test_firebase_google_callback_authenticates_whitelisted_member_and_rejects_unrecognized_email(): void
    {
        $this->seed(DatabaseSeeder::class);

        Http::fake([
            'https://identitytoolkit.googleapis.com/*' => Http::sequence()
                ->push([
                    'users' => [[
                        'localId' => 'fb_uid_123',
                        'email' => 'alex@household.local',
                        'photoUrl' => 'https://example.com/avatar.png',
                    ]],
                ], 200)
                ->push([
                    'users' => [[
                        'localId' => 'fb_uid_999',
                        'email' => 'stranger@gmail.com',
                    ]],
                ], 200),
        ]);

        // 1. Whitelisted member succeeds
        $this->post(route('auth.firebase.callback'), ['id_token' => 'valid_firebase_token'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->post(route('logout'));

        // 2. Unrecognized email is rejected by whitelist
        $this->post(route('auth.firebase.callback'), ['id_token' => 'stranger_firebase_token'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }
}

