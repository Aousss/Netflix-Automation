@section('title', 'Dashboard — Netflix Household Hub')

@section('body-attrs')
    x-data="householdPortal({
        csrfToken: '{{ csrf_token() }}',
        imapFetchUrl: '{{ route('imap.fetch') }}',
        otpRequestUrl: '{{ route('vault.request-otp') }}',
        vaultRevealUrl: '{{ route('vault.reveal') }}',
        graceBypassUrl: '{{ route('vault.grace-bypass') }}',
        masterEmail: '{{ e($settings->netflix_email) }}',
        profileName: '{{ e($user->netflix_profile_name) }}',
        profilePin: '{{ e($user->netflix_profile_pin ?? '----') }}',
        isStepUpVerified: {{ $isStepUpVerified ? 'true' : 'false' }},
        isOverdueLocked: {{ (! $user->canAccessVault($myPayment)) ? 'true' : 'false' }},
        initialAdminTab: '{{ session('admin_tab', $pendingReceipts->count() > 0 ? 'queue' : 'vault') }}',
        initialAdminOpen: {{ session()->has('admin_tab') ? 'true' : 'false' }}
    })"
    @keydown.escape.window="adminDrawerOpen = false; otpModalOpen = false; paymentModalOpen = false"
@endsection

@push('head')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@extends('layouts.app')

@section('content')

    <!-- ==================== TOP NAVBAR ==================== -->
    <header class="sticky top-0 z-30 bg-zinc-950/85 backdrop-blur-xl border-b border-zinc-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <!-- Left: Brand -->
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-red-600 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-red-600/30" aria-hidden="true">
                    N
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold tracking-tight text-white text-base sm:text-lg">Household Hub</span>
                        <span class="hidden sm:inline-flex items-center badge bg-zinc-900 border-zinc-800 text-zinc-400">
                            {{ $settings->plan_name }}
                        </span>
                    </div>
                    <p class="text-[11px] text-zinc-500 hidden sm:block">Shared household management</p>
                </div>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center gap-2.5 sm:gap-3">
                @if (app()->environment('local') || config('app.debug'))
                    <!-- Quick Demo Profile Switcher -->
                    <div class="relative" x-data="{ openSwitch: false }">
                        <button @click="openSwitch = !openSwitch" type="button"
                                :aria-expanded="openSwitch.toString()"
                                aria-haspopup="true"
                                class="btn btn-secondary px-2.5 py-1.5 text-xs min-h-[44px] flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400" aria-hidden="true"></span>
                            <span class="hidden md:inline">Switch Member</span>
                            <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="openSwitch" @click.outside="openSwitch = false" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute right-0 mt-2 w-64 rounded-2xl surface-card shadow-2xl py-2 z-50"
                             role="menu">
                            <div class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-500 border-b border-zinc-800">
                                Switch Profile
                            </div>
                            @foreach ($members as $switchMember)
                                <form method="POST" action="{{ route('auth.demo.switch', $switchMember) }}">
                                    @csrf
                                    <button type="submit" role="menuitem"
                                            class="w-full px-3 py-2 text-left text-xs hover:bg-zinc-800 flex items-center justify-between transition min-h-[44px] {{ $switchMember->id === $user->id ? 'bg-zinc-800/60 text-white font-semibold' : 'text-zinc-300' }}">
                                        <span>{{ $switchMember->name }}</span>
                                        @if ($switchMember->id === $user->id)
                                            <span class="badge bg-red-600/20 border-red-600/30 text-red-400">Current</span>
                                        @else
                                            <span class="badge bg-zinc-950 border-zinc-800 text-zinc-400">{{ $switchMember->role }}</span>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($user->isAdmin())
                    <button @click="openAdminPanel()" type="button"
                            class="btn relative px-3 py-1.5 rounded-xl bg-red-600/15 hover:bg-red-600/25 border border-red-600/40 text-red-300 text-xs font-semibold min-h-[44px]">
                        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="hidden sm:inline">Admin</span>
                        @if ($pendingReceipts->count() > 0)
                            <span class="badge px-1.5 py-0.5 bg-amber-500 text-zinc-950 font-extrabold text-[10px] leading-none">
                                {{ $pendingReceipts->count() }}
                            </span>
                        @endif
                    </button>
                @endif

                <!-- Member Badge + Avatar -->
                <div class="flex items-center gap-2.5 pl-2 border-l border-zinc-800">
                    <div class="w-8 h-8 rounded-xl bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-xs text-white" aria-hidden="true">
                        {{ strtoupper(substr($user->netflix_profile_name, 0, 2)) }}
                    </div>
                    <div class="hidden sm:block text-left">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold text-zinc-100">{{ $user->name }}</span>
                            <span class="badge px-1.5 py-0.5 {{ $user->isAdmin() ? 'bg-red-600/20 text-red-400 border-red-600/30' : 'bg-zinc-800 text-zinc-400' }}">
                                {{ $user->role }}
                            </span>
                        </div>
                        <div class="text-[11px] text-zinc-500">{{ $user->netflix_profile_name }}</div>
                    </div>
                </div>

                <!-- Logout -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" aria-label="Sign out"
                            class="btn btn-secondary p-2 min-h-[44px] min-w-[44px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- ==================== MAIN CONTENT ==================== -->
    <main id="main-content" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">

        <!-- Flash Notifications -->
        @if (session('status'))
            <div class="p-4 rounded-2xl bg-emerald-950/60 border border-emerald-800/80 text-emerald-200 text-sm flex items-center gap-2.5 animate-enter" role="alert">
                <svg class="w-5 h-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-950/60 border border-rose-800/80 text-rose-200 text-sm space-y-1 animate-enter" role="alert">
                @foreach ($errors->all() as $err)
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400 shrink-0" aria-hidden="true"></span>
                        {{ $err }}
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Welcome Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-enter">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight" style="text-wrap: balance">
                    Welcome back, {{ explode(' ', $user->name)[0] }}
                </h1>
                <p class="text-sm text-zinc-400 mt-0.5" style="text-wrap: balance">Here's everything you need for your Netflix household.</p>
            </div>
            <div class="text-xs text-zinc-500 tabular-nums">
                {{ now()->format('l, d M Y') }}
            </div>
        </div>

        <!-- Toast Alert -->
        <div x-show="toastMessage" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-4"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-4"
             class="fixed bottom-5 right-5 z-50 px-5 py-3.5 rounded-2xl surface-card text-zinc-100 text-sm font-medium shadow-2xl flex items-center gap-2.5"
             aria-hidden="true">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shrink-0" aria-hidden="true"></span>
            <span x-text="toastMessage"></span>
        </div>

        <!-- ==================== BENTO GRID ==================== -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- ==============================
                 CARD 1: TV Verification Codes
                 ============================== -->
            <section class="lg:col-span-7 surface-card p-5 sm:p-6 flex flex-col justify-between space-y-5 animate-enter-d1"
                     aria-labelledby="heading-tv-codes">
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-red-400">
                                <span class="w-2 h-2 rounded-full bg-red-500 animate-ping" aria-hidden="true"></span>
                                TV Verification
                            </div>
                            <h2 id="heading-tv-codes" class="text-lg sm:text-xl font-bold text-white mt-1 tracking-tight" style="text-wrap: balance">Verification Codes</h2>
                            <p class="text-xs text-zinc-400 mt-0.5">
                                When Netflix asks you to verify, tap <strong class="text-zinc-200">"Send Email"</strong> on your TV, then fetch the code here.
                            </p>
                        </div>

                        <button @click="fetchVerificationEmails()" :disabled="imapLoading" type="button"
                                class="btn btn-primary px-4 py-3 text-sm min-h-[44px] shrink-0">
                            <svg x-show="imapLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <svg x-show="!imapLoading" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span x-text="imapLoading ? 'Checking...' : 'Fetch code'"></span>
                        </button>
                    </div>

                    <!-- Mode Notice -->
                    <template x-if="imapMode === 'demo'">
                        <div class="px-3.5 py-2.5 rounded-xl bg-amber-500/5 border border-amber-500/20 text-[11px] text-zinc-400 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0" aria-hidden="true"></span>
                            <span>Running in <strong class="text-amber-400">demo mode</strong> — sample codes shown for testing.</span>
                        </div>
                    </template>

                    <template x-if="imapError">
                        <div class="p-3.5 rounded-xl bg-rose-950/60 border border-rose-800 text-xs text-rose-200 flex items-center gap-2" role="alert">
                            <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-text="imapError"></span>
                        </div>
                    </template>

                    <!-- Verification Cards -->
                    <div class="space-y-3" x-show="imapItems.length > 0" x-cloak>
                        <template x-for="item in imapItems" :key="item.id">
                            <div class="p-4 surface-inner hover:border-zinc-700 transition flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="badge bg-red-600/20 border-red-600/30 text-red-300 text-xs"
                                              x-text="item.profile_name"></span>
                                        <span class="badge bg-zinc-900 border-zinc-800 text-zinc-300 text-xs"
                                              x-text="item.device_type"></span>
                                        <span class="text-[11px] font-mono tabular-nums px-2 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20"
                                              x-text="'Expires ' + formatCountdown(item.remaining_seconds)"></span>
                                    </div>
                                    <div class="text-sm font-semibold text-zinc-100" x-text="item.subject"></div>
                                    <div class="text-xs text-zinc-500" x-text="item.received_human"></div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <template x-if="item.code">
                                        <button @click="copyText(item.code, 'Code copied!')" type="button"
                                                aria-label="Copy verification code"
                                                class="btn btn-secondary px-3.5 py-2.5 text-emerald-400 font-mono font-bold text-base tracking-widest min-h-[44px]">
                                            <span x-text="item.code"></span>
                                        </button>
                                    </template>
                                    <template x-if="item.approval_url">
                                        <a :href="item.approval_url" target="_blank" rel="noopener"
                                           class="btn btn-success px-4 py-2.5 text-xs min-h-[44px]">
                                            <span>Approve</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Empty State -->
                    <div x-show="imapItems.length === 0 && !imapLoading"
                         class="p-8 rounded-xl bg-zinc-950/60 border border-dashed border-zinc-800 text-center space-y-2">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-zinc-900 border border-zinc-800 flex items-center justify-center">
                            <svg class="w-5 h-5 text-zinc-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-zinc-300">No verification codes found</p>
                        <p class="text-xs text-zinc-500">When Netflix asks you to verify, click <strong class="text-zinc-400">Fetch code</strong> above.</p>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-800/80 flex items-center justify-between text-[11px] text-zinc-500">
                    <span>Checks emails from the last 15 minutes</span>
                    <span x-show="imapItems.length > 0" x-text="imapItems.length + ' code(s) found'" class="tabular-nums"></span>
                </div>
            </section>

            <!-- ==============================
                 CARD 2: Account Credentials
                 ============================== -->
            <section class="lg:col-span-5 relative surface-card p-5 sm:p-6 flex flex-col justify-between space-y-5 overflow-hidden animate-enter-d2"
                     aria-labelledby="heading-credentials">

                <!-- Payment Lock Overlay -->
                <div x-show="isOverdueLocked" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="absolute inset-0 z-20 bg-zinc-950/90 backdrop-blur-md p-6 flex flex-col items-center justify-center text-center space-y-4">
                    <div class="w-14 h-14 rounded-2xl bg-rose-500/15 border border-rose-500/30 flex items-center justify-center text-rose-400">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div class="space-y-2 max-w-xs">
                        <h3 class="text-base font-bold text-white tracking-tight">Credentials Locked</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed" style="text-wrap: balance">
                            Your monthly payment for <strong class="text-zinc-200">{{ $currentCycle?->cycle_month }}</strong> is overdue. Upload your transfer receipt to regain access.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-2.5 pt-1">
                        <button @click="paymentModalOpen = true" type="button"
                                class="btn btn-primary px-4 py-2.5 text-xs min-h-[44px]">
                            Upload receipt
                        </button>
                        @if ($user->grace_bypass_used_at === null)
                            <button @click="triggerGraceBypass()" type="button"
                                    class="btn px-3.5 py-2.5 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 text-amber-300 text-xs font-medium min-h-[44px]">
                                24h Emergency Access
                            </button>
                        @endif
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Encrypted Vault</span>
                            <h2 id="heading-credentials" class="text-lg sm:text-xl font-bold text-white mt-0.5 tracking-tight" style="text-wrap: balance">Account Credentials</h2>
                        </div>
                        <span class="badge px-2.5 py-1 bg-zinc-950 border-zinc-800 text-[11px] text-zinc-400">
                            Updated {{ $settings->password_updated_at?->diffForHumans() ?? 'recently' }}
                        </span>
                    </div>

                    <!-- Master Netflix Email -->
                    <div class="p-3.5 surface-inner flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-[11px] uppercase tracking-wider text-zinc-500">Netflix Email</div>
                            <div class="text-sm font-mono font-medium text-zinc-100 mt-0.5 truncate">{{ $settings->netflix_email }}</div>
                        </div>
                        <button @click="copyText(masterEmail, 'Email copied!')" type="button"
                                class="btn btn-secondary px-3 py-1.5 text-xs min-h-[44px] shrink-0">
                            Copy
                        </button>
                    </div>

                    <!-- Master Netflix Password -->
                    <div class="p-3.5 surface-inner space-y-2"
                         :class="revealedPassword ? 'border-emerald-500/30 pulse-border' : ''">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-[11px] uppercase tracking-wider text-zinc-500 flex items-center gap-2">
                                    <span>Password</span>
                                    <template x-if="remaskCountdown > 0">
                                        <span class="text-amber-400 font-mono text-[10px] tabular-nums" x-text="'Hidden in ' + remaskCountdown + 's'"></span>
                                    </template>
                                </div>
                                <div class="text-sm font-mono font-bold tracking-wider mt-0.5"
                                     :class="revealedPassword ? 'text-emerald-400' : 'text-zinc-400'"
                                     x-text="revealedPassword ? revealedPassword : '••••••••••••'"></div>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <template x-if="!revealedPassword">
                                    <button @click="initiateVaultReveal()" type="button"
                                            class="btn btn-primary px-3 py-1.5 text-xs min-h-[44px]">
                                        Reveal
                                    </button>
                                </template>
                                <template x-if="revealedPassword">
                                    <button @click="copyText(revealedPassword, 'Password copied!')" type="button"
                                            class="btn btn-success px-3 py-1.5 text-xs min-h-[44px]">
                                        Copy
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Profile Name & PIN -->
                    <div class="p-3.5 surface-inner flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-[11px] uppercase tracking-wider text-zinc-500">Your Profile &amp; PIN</div>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-sm font-semibold text-white">{{ $user->netflix_profile_name }}</span>
                                <span class="text-xs px-2 py-0.5 rounded bg-zinc-900 border border-zinc-700 font-mono text-amber-300"
                                      x-text="pinVisible ? ('PIN: ' + profilePin) : 'PIN: ••••'"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button @click="pinVisible = !pinVisible" type="button"
                                    class="btn btn-secondary px-2.5 py-1.5 text-xs min-h-[44px]"
                                    x-text="pinVisible ? 'Hide' : 'Show'"
                                    :aria-label="pinVisible ? 'Hide PIN' : 'Show PIN'"></button>
                            <button @click="copyText(profilePin, 'PIN copied!')" type="button"
                                    aria-label="Copy PIN"
                                    class="btn btn-secondary px-2.5 py-1.5 text-xs min-h-[44px]">
                                Copy
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-800/80 flex items-center justify-between text-[11px] text-zinc-500">
                    <span>Secured with email verification</span>
                    <span>All access is logged</span>
                </div>
            </section>

            <!-- ==============================
                 CARD 3: My Monthly Share
                 ============================== -->
            <section class="lg:col-span-5 surface-card p-5 sm:p-6 flex flex-col justify-between space-y-5 animate-enter-d2"
                     aria-labelledby="heading-my-share">
                @php
                    $myStatus = $myPayment?->status ?? 'pending';
                    $badgeStyle = match ($myStatus) {
                        'paid' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                        'verifying' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                        default => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                    };
                    $badgeLabel = match ($myStatus) {
                        'paid' => 'PAID',
                        'verifying' => 'VERIFYING',
                        'overdue' => 'OVERDUE',
                        default => 'PENDING',
                    };
                @endphp

                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                {{ $currentCycle?->cycle_month ?? now()->format('Y-m') }}
                            </span>
                            <h2 id="heading-my-share" class="text-lg sm:text-xl font-bold text-white mt-0.5 tracking-tight" style="text-wrap: balance">My Monthly Share</h2>
                        </div>
                        <span class="badge px-3 py-1 {{ $badgeStyle }}">
                            {{ $badgeLabel }}
                        </span>
                    </div>

                    <!-- Share Summary -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3.5 surface-inner">
                            <div class="text-[11px] text-zinc-500 uppercase">Amount Due</div>
                            <div class="text-2xl font-extrabold text-white mt-0.5 tabular-nums">
                                RM {{ number_format((float) ($myPayment?->amount ?? $user->monthly_share_amount), 2) }}
                            </div>
                            <div class="text-[11px] text-zinc-500 mt-0.5">
                                Due {{ $currentCycle?->due_date?->format('d M Y') ?? '5th of month' }}
                            </div>
                        </div>

                        <div class="p-3.5 surface-inner">
                            <div class="text-[11px] text-zinc-500 uppercase">Credit Balance</div>
                            <div class="text-2xl font-extrabold text-emerald-400 mt-0.5 tabular-nums">
                                RM {{ number_format((float) $user->credit_balance, 2) }}
                            </div>
                            <div class="text-[11px] text-zinc-500 mt-0.5">Applied automatically</div>
                        </div>
                    </div>

                    <!-- Bank Account Quick Copy -->
                    <div class="p-3.5 surface-inner flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-[11px] text-zinc-500">{{ $settings->bank_name ?? 'Maybank' }} &bull; {{ $settings->bank_account_holder ?? 'Household Leader' }}</div>
                            <div class="text-sm font-mono font-bold text-zinc-100 mt-0.5">{{ $settings->bank_account_number ?? '1642 8940 3192' }}</div>
                        </div>
                        <button @click="copyText('{{ preg_replace('/\s+/', '', $settings->bank_account_number ?? '164289403192') }}', 'Account number copied!')"
                                type="button"
                                aria-label="Copy account number"
                                class="btn btn-secondary px-3 py-1.5 text-xs min-h-[44px] shrink-0">
                            Copy
                        </button>
                    </div>

                    @if ($myPayment?->reference_note)
                        <div class="text-xs text-zinc-400 flex items-center justify-between px-1">
                            <span>Reference: <strong class="text-zinc-200">{{ $myPayment->reference_note }}</strong></span>
                            @if ($myPayment->receipt_path)
                                <a href="{{ route('payments.receipt', $myPayment) }}" target="_blank"
                                   class="text-red-400 hover:text-red-300 underline">View Receipt</a>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="pt-2">
                    <button @click="paymentModalOpen = true" type="button"
                            class="btn btn-primary w-full py-3 px-4 text-sm min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Pay via DuitNow</span>
                    </button>
                </div>
            </section>

            <!-- ==============================
                 CARD 4: Household Billing Ledger
                 ============================== -->
            <section class="lg:col-span-7 surface-card p-5 sm:p-6 flex flex-col justify-between space-y-5 animate-enter-d3"
                     x-data="{ ledgerTab: 'current' }"
                     aria-labelledby="heading-billing">
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">All Members</span>
                            <h2 id="heading-billing" class="text-lg sm:text-xl font-bold text-white mt-0.5 tracking-tight" style="text-wrap: balance">Billing Overview</h2>
                        </div>

                        <!-- Tab Switcher -->
                        <div class="inline-flex p-1 rounded-xl bg-zinc-950 border border-zinc-800 text-xs" role="tablist" aria-label="Billing view">
                            <button @click="ledgerTab = 'current'" type="button"
                                    role="tab"
                                    :aria-selected="(ledgerTab === 'current').toString()"
                                    aria-controls="billing-panel-current"
                                    id="billing-tab-current"
                                    :class="ledgerTab === 'current' ? 'bg-zinc-800 text-white font-semibold shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                                    class="px-3 py-1.5 rounded-lg transition min-h-[44px]">
                                This Month
                            </button>
                            <button @click="ledgerTab = 'history'" type="button"
                                    role="tab"
                                    :aria-selected="(ledgerTab === 'history').toString()"
                                    aria-controls="billing-panel-history"
                                    id="billing-tab-history"
                                    :class="ledgerTab === 'history' ? 'bg-zinc-800 text-white font-semibold shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                                    class="px-3 py-1.5 rounded-lg transition min-h-[44px]">
                                History
                            </button>
                        </div>
                    </div>

                    <!-- Current Month Matrix -->
                    <div x-show="ledgerTab === 'current'"
                         id="billing-panel-current"
                         role="tabpanel"
                         aria-labelledby="billing-tab-current"
                         class="overflow-x-auto -mx-1 px-1">
                        <table class="w-full text-left border-collapse">
                            <caption class="sr-only">Current month billing status for all household members</caption>
                            <thead>
                                <tr class="border-b border-zinc-800 text-[11px] uppercase tracking-wider text-zinc-500">
                                    <th scope="col" class="py-2.5 pr-3">Member</th>
                                    <th scope="col" class="py-2.5 px-3">Share</th>
                                    <th scope="col" class="py-2.5 px-3">Status</th>
                                    <th scope="col" class="py-2.5 pl-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60 text-sm">
                                @if ($currentCycle)
                                    @foreach ($currentCycle->payments as $payment)
                                        @php
                                            $chip = match ($payment->status) {
                                                'paid' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                                                'verifying' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                                                'overdue' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                                                default => 'bg-zinc-800 text-zinc-300 border-zinc-700',
                                            };
                                        @endphp
                                        <tr class="hover:bg-zinc-950/40 transition">
                                            <td class="py-3 pr-3">
                                                <div class="font-semibold text-zinc-100 flex items-center gap-2">
                                                    <span>{{ $payment->user?->name }}</span>
                                                    @if ($payment->user_id === $user->id)
                                                        <span class="badge text-[10px] px-1.5 py-0.5 bg-red-600/20 text-red-400 border-red-600/30">YOU</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-zinc-500">{{ $payment->user?->netflix_profile_name }}</div>
                                            </td>
                                            <td class="py-3 px-3 font-mono text-xs text-zinc-200 tabular-nums">
                                                RM {{ number_format((float) $payment->amount, 2) }}
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="badge px-2.5 py-0.5 {{ $chip }}">
                                                    {{ $payment->status }}
                                                </span>
                                            </td>
                                            <td class="py-3 pl-3 text-right text-xs text-zinc-400">
                                                <div class="flex items-center justify-end gap-2">
                                                    <span class="hidden sm:inline">{{ $payment->reference_note ?? '—' }}</span>
                                                    @if ($user->isAdmin() && $payment->status !== 'paid')
                                                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="btn btn-success px-2.5 py-1 text-[11px] min-h-[44px]">
                                                                Approve
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <!-- Historical Ledger -->
                    <div x-show="ledgerTab === 'history'" x-cloak
                         id="billing-panel-history"
                         role="tabpanel"
                         aria-labelledby="billing-tab-history"
                         class="space-y-3 max-h-72 overflow-y-auto overscroll-contain pr-1">
                        @forelse ($historicalCycles as $hCycle)
                            <div class="p-3.5 surface-inner flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-bold text-white flex items-center gap-2">
                                        <span>{{ $hCycle->cycle_month }}</span>
                                        <span class="text-xs font-mono text-zinc-400 tabular-nums">(RM {{ number_format((float) $hCycle->total_cost, 2) }})</span>
                                    </div>
                                    <div class="text-xs text-zinc-500">{{ $hCycle->notes }}</div>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($hCycle->payments as $hp)
                                        <span class="badge px-2 py-1 text-[11px] {{ $hp->status === 'paid' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-zinc-900 border-zinc-800 text-zinc-400' }}">
                                            {{ $hp->user?->netflix_profile_name }}: {{ strtoupper($hp->status) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-sm text-zinc-500">No previous billing cycles yet.</div>
                        @endforelse
                    </div>
                </div>

                @php
                    $paidTotal = $currentCycle ? $currentCycle->payments->where('status', 'paid')->sum('amount') : 0;
                    $cycleTotal = $currentCycle?->total_cost ?? 55.00;
                    $progressPct = $cycleTotal > 0 ? min(100, round(($paidTotal / $cycleTotal) * 100)) : 0;
                @endphp
                <div class="pt-3 border-t border-zinc-800/80 space-y-2">
                    <!-- Progress bar -->
                    <div class="w-full h-1.5 rounded-full bg-zinc-800 overflow-hidden" role="progressbar" aria-valuenow="{{ $progressPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Payment collection progress">
                        <div class="h-full rounded-full transition-all duration-500 {{ $progressPct === 100 ? 'bg-emerald-500' : 'bg-red-600' }}"
                             style="width: {{ $progressPct }}%"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="tabular-nums">Collected: <strong class="text-emerald-400">RM {{ number_format((float) $paidTotal, 2) }}</strong> / RM {{ number_format((float) $cycleTotal, 2) }}</span>
                        <span>Due on the {{ $settings->billing_day_of_month }}{{ in_array($settings->billing_day_of_month, [1,21,31]) ? 'st' : (in_array($settings->billing_day_of_month, [2,22]) ? 'nd' : (in_array($settings->billing_day_of_month, [3,23]) ? 'rd' : 'th')) }}</span>
                    </div>
                </div>
            </section>
        </div>

        <!-- ==================== ADMIN SECTION (INLINE + MODAL OVERLAY) ==================== -->
        @if ($user->isAdmin())
            <div :class="adminDrawerOpen ? 'fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto' : ''"
                 @click.self="adminDrawerOpen = false">
            <section id="admin-panel"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="admin-panel-title"
                     x-bind:aria-hidden="(!adminDrawerOpen).toString()"
                     :class="adminDrawerOpen ? 'w-full max-w-5xl max-h-[90vh] overflow-y-auto overscroll-contain shadow-2xl ring-2 ring-red-600/50' : ''"
                     class="surface-card border-red-900/40 p-5 sm:p-6 space-y-6 animate-enter-d3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-800 pb-4">
                    <div>
                        <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-red-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            Admin Panel
                        </div>
                        <h2 id="admin-panel-title" class="text-lg sm:text-xl font-bold text-white mt-0.5 tracking-tight">Manage Household</h2>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex flex-wrap gap-1.5 bg-zinc-950 p-1 rounded-xl border border-zinc-800 text-xs" role="tablist" aria-label="Admin panel sections">
                            <button @click="adminTab = 'queue'" type="button"
                                    role="tab"
                                    :aria-selected="(adminTab === 'queue').toString()"
                                    aria-controls="admin-panel-queue"
                                    id="admin-tab-queue"
                                    :class="adminTab === 'queue' ? 'bg-red-600 text-white font-semibold' : 'text-zinc-400 hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg transition min-h-[44px]">
                                Receipts
                                @if ($pendingReceipts->count() > 0)
                                    <span class="ml-1 badge px-1.5 py-0.5 bg-amber-500 text-zinc-950 font-extrabold text-[10px] leading-none">{{ $pendingReceipts->count() }}</span>
                                @endif
                            </button>
                            <button @click="adminTab = 'vault'" type="button"
                                    role="tab"
                                    :aria-selected="(adminTab === 'vault').toString()"
                                    aria-controls="admin-panel-vault"
                                    id="admin-tab-vault"
                                    :class="adminTab === 'vault' ? 'bg-red-600 text-white font-semibold' : 'text-zinc-400 hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg transition min-h-[44px]">
                                Settings
                            </button>
                            <button @click="adminTab = 'members'" type="button"
                                    role="tab"
                                    :aria-selected="(adminTab === 'members').toString()"
                                    aria-controls="admin-panel-members"
                                    id="admin-tab-members"
                                    :class="adminTab === 'members' ? 'bg-red-600 text-white font-semibold' : 'text-zinc-400 hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg transition min-h-[44px]">
                                Members
                            </button>
                            <button @click="adminTab = 'audit'" type="button"
                                    role="tab"
                                    :aria-selected="(adminTab === 'audit').toString()"
                                    aria-controls="admin-panel-audit"
                                    id="admin-tab-audit"
                                    :class="adminTab === 'audit' ? 'bg-red-600 text-white font-semibold' : 'text-zinc-400 hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg transition min-h-[44px]">
                                Audit Log
                            </button>
                        </div>

                        <button x-show="adminDrawerOpen" x-cloak @click="adminDrawerOpen = false" type="button"
                                aria-label="Close admin overlay"
                                class="btn btn-secondary p-2 min-h-[44px] min-w-[44px]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Admin Tab 1: Pending Receipts -->
                <div x-show="adminTab === 'queue'" x-transition
                     id="admin-panel-queue"
                     role="tabpanel"
                     aria-labelledby="admin-tab-queue"
                     class="space-y-4">
                    @if ($pendingReceipts->isEmpty())
                        <div class="p-8 rounded-xl bg-zinc-950/60 border border-dashed border-zinc-800 text-center space-y-2">
                            <div class="w-10 h-10 mx-auto rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-zinc-300">All caught up!</p>
                            <p class="text-xs text-zinc-500">No receipts waiting for review.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($pendingReceipts as $pending)
                                <div class="p-4 rounded-xl bg-zinc-950 border border-amber-500/30 flex flex-col justify-between gap-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <span class="badge px-2 py-0.5 bg-amber-500/20 text-amber-300">
                                                {{ $pending->billingCycle?->cycle_month }}
                                            </span>
                                            <h3 class="text-base font-bold text-white mt-1 tracking-tight">{{ $pending->user?->name }}</h3>
                                            <p class="text-xs text-zinc-400">
                                                <strong class="text-emerald-400 tabular-nums">RM {{ number_format((float) $pending->amount, 2) }}</strong>
                                                @if ($pending->reference_note)
                                                    &bull; {{ $pending->reference_note }}
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-zinc-500 mt-0.5">{{ $pending->paid_at?->diffForHumans() }}</p>
                                        </div>

                                        @if ($pending->receipt_path)
                                            <a href="{{ route('payments.receipt', $pending) }}" target="_blank"
                                               class="btn btn-secondary px-3 py-1.5 text-xs min-h-[44px] shrink-0">
                                                View
                                            </a>
                                        @endif
                                    </div>

                                    @if ($pending->receipt_path)
                                        <div class="rounded-xl overflow-hidden border border-zinc-800 bg-zinc-900 max-h-56 flex items-center justify-center">
                                            <img src="{{ route('payments.receipt', $pending) }}" alt="Receipt from {{ $pending->user?->name }}" class="max-h-52 object-contain">
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-2 pt-2 border-t border-zinc-800">
                                        <form method="POST" action="{{ route('admin.payments.verify', $pending) }}" class="flex-1">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button type="submit"
                                                    class="btn btn-success w-full py-2.5 px-3 text-xs min-h-[44px]">
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.payments.verify', $pending) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject">
                                            <button type="submit"
                                                    class="btn btn-danger py-2.5 px-3 text-xs min-h-[44px]">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Admin Tab 2: Account Settings -->
                <div x-show="adminTab === 'vault'" x-cloak x-transition
                     id="admin-panel-vault"
                     role="tabpanel"
                     aria-labelledby="admin-tab-vault">
                    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
                          class="space-y-6">
                        @csrf

                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold text-zinc-200">Netflix Account</legend>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="settings-netflix-email" class="block text-xs text-zinc-400 mb-1.5">Email</label>
                                    <input type="email" name="netflix_email" id="settings-netflix-email" value="{{ $settings->netflix_email }}" required autocomplete="email"
                                           class="input">
                                </div>
                                <div>
                                    <label for="settings-netflix-password" class="block text-xs text-zinc-400 mb-1.5">New Password <span class="text-zinc-600">(leave blank to keep current)</span></label>
                                    <input type="text" name="netflix_password" id="settings-netflix-password" placeholder="Enter new password..." autocomplete="off"
                                           class="input">
                                </div>
                                <div>
                                    <label for="settings-plan-name" class="block text-xs text-zinc-400 mb-1.5">Plan Name</label>
                                    <input type="text" name="plan_name" id="settings-plan-name" value="{{ $settings->plan_name }}" required
                                           class="input">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold text-zinc-200">Billing &amp; Payment</legend>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="settings-monthly-cost" class="block text-xs text-zinc-400 mb-1.5">Monthly Cost (RM)</label>
                                    <input type="number" step="0.01" name="total_monthly_cost" id="settings-monthly-cost" value="{{ $settings->total_monthly_cost }}" required
                                           class="input tabular-nums">
                                </div>
                                <div>
                                    <label for="settings-billing-day" class="block text-xs text-zinc-400 mb-1.5">Billing Day (1-28)</label>
                                    <input type="number" min="1" max="28" name="billing_day_of_month" id="settings-billing-day" value="{{ $settings->billing_day_of_month }}" required
                                           class="input tabular-nums">
                                </div>
                                <div>
                                    <label for="settings-duitnow-qr" class="block text-xs text-zinc-400 mb-1.5">DuitNow QR Image</label>
                                    <input type="file" name="duitnow_qr" id="settings-duitnow-qr" accept=".jpg,.jpeg,.png,.svg,.webp"
                                           class="w-full text-xs text-zinc-400 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:bg-zinc-800 file:text-zinc-200 file:cursor-pointer">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold text-zinc-200">Bank Details</legend>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="settings-bank-name" class="block text-xs text-zinc-400 mb-1.5">Bank Name</label>
                                    <input type="text" name="bank_name" id="settings-bank-name" value="{{ $settings->bank_name }}"
                                           class="input">
                                </div>
                                <div>
                                    <label for="settings-bank-account" class="block text-xs text-zinc-400 mb-1.5">Account Number</label>
                                    <input type="text" name="bank_account_number" id="settings-bank-account" value="{{ $settings->bank_account_number }}"
                                           class="input font-mono">
                                </div>
                                <div>
                                    <label for="settings-bank-holder" class="block text-xs text-zinc-400 mb-1.5">Account Holder</label>
                                    <input type="text" name="bank_account_holder" id="settings-bank-holder" value="{{ $settings->bank_account_holder }}"
                                           class="input">
                                </div>
                            </div>
                        </fieldset>

                        <div class="flex justify-end pt-2">
                            <button type="submit"
                                    class="btn btn-primary px-5 py-2.5 text-sm min-h-[44px]">
                                Save changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Admin Tab 3: Member Editor -->
                <div x-show="adminTab === 'members'" x-cloak x-transition
                     id="admin-panel-members"
                     role="tabpanel"
                     aria-labelledby="admin-tab-members"
                     class="space-y-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-zinc-400">Manage household members, profiles, and billing shares.</p>
                        <form method="POST" action="{{ route('admin.billing.generate') }}">
                            @csrf
                            <button type="submit"
                                    class="btn btn-secondary px-3.5 py-1.5 text-xs min-h-[44px]">
                                Generate new cycle
                            </button>
                        </form>
                    </div>

                    <div class="space-y-3">
                        @foreach ($members as $m)
                            <form method="POST" action="{{ route('admin.members.update', $m) }}"
                                  class="p-4 surface-inner hover:border-zinc-700 transition grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                                @csrf
                                <div>
                                    <label for="member-{{ $m->id }}-name" class="block text-[10px] uppercase text-zinc-500 mb-1">Name</label>
                                    <input type="text" name="name" id="member-{{ $m->id }}-name" value="{{ $m->name }}" required autocomplete="name"
                                           class="input text-xs">
                                </div>
                                <div>
                                    <label for="member-{{ $m->id }}-email" class="block text-[10px] uppercase text-zinc-500 mb-1">Email</label>
                                    <input type="email" name="email" id="member-{{ $m->id }}-email" value="{{ $m->email }}" required autocomplete="email"
                                           class="input text-xs">
                                </div>
                                <div>
                                    <label for="member-{{ $m->id }}-profile" class="block text-[10px] uppercase text-zinc-500 mb-1">Profile</label>
                                    <input type="text" name="netflix_profile_name" id="member-{{ $m->id }}-profile" value="{{ $m->netflix_profile_name }}" required
                                           class="input text-xs">
                                </div>
                                <div>
                                    <label for="member-{{ $m->id }}-pin" class="block text-[10px] uppercase text-zinc-500 mb-1">PIN</label>
                                    <input type="text" name="netflix_profile_pin" id="member-{{ $m->id }}-pin" value="{{ $m->netflix_profile_pin }}" maxlength="4"
                                           class="input text-xs font-mono text-amber-300">
                                </div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <div>
                                        <label for="member-{{ $m->id }}-share" class="block text-[10px] uppercase text-zinc-500 mb-1">Share</label>
                                        <input type="number" step="0.01" name="monthly_share_amount" id="member-{{ $m->id }}-share" value="{{ $m->monthly_share_amount }}" required
                                               class="input text-xs tabular-nums">
                                    </div>
                                    <div>
                                        <label for="member-{{ $m->id }}-credit" class="block text-[10px] uppercase text-zinc-500 mb-1">Credit</label>
                                        <input type="number" step="0.01" name="credit_balance" id="member-{{ $m->id }}-credit" value="{{ $m->credit_balance }}" required
                                               class="input text-xs text-emerald-400 tabular-nums">
                                    </div>
                                </div>
                                <div class="flex items-center justify-end gap-2.5">
                                    <label class="inline-flex items-center gap-1.5 text-xs text-zinc-400 cursor-pointer">
                                        <input type="checkbox" name="is_active" value="1" {{ $m->is_active ? 'checked' : '' }}
                                               class="rounded bg-zinc-900 border-zinc-700 text-red-600 focus:ring-red-600 focus:ring-offset-0">
                                        <span>Active</span>
                                    </label>
                                    <button type="submit"
                                            class="btn btn-primary px-3.5 py-2 text-xs min-h-[44px]">
                                        Save
                                    </button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>

                <!-- Admin Tab 4: Audit Log -->
                <div x-show="adminTab === 'audit'" x-cloak x-transition
                     id="admin-panel-audit"
                     role="tabpanel"
                     aria-labelledby="admin-tab-audit"
                     class="overflow-x-auto">
                    @if ($recentLogs->isEmpty())
                        <div class="p-8 rounded-xl bg-zinc-950/60 border border-dashed border-zinc-800 text-center text-sm text-zinc-500">
                            No activity logged yet.
                        </div>
                    @else
                        <table class="w-full text-left text-xs">
                            <caption class="sr-only">Recent audit log entries for household administration</caption>
                            <thead>
                                <tr class="border-b border-zinc-800 text-zinc-500 uppercase">
                                    <th scope="col" class="py-2.5 pr-3">When</th>
                                    <th scope="col" class="py-2.5 px-3">Who</th>
                                    <th scope="col" class="py-2.5 px-3">Action</th>
                                    <th scope="col" class="py-2.5 px-3">Details</th>
                                    <th scope="col" class="py-2.5 pl-3 text-right">IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60">
                                @foreach ($recentLogs as $log)
                                    <tr class="hover:bg-zinc-950/40 transition">
                                        <td class="py-2.5 pr-3 text-zinc-400 font-mono tabular-nums whitespace-nowrap">{{ $log->created_at?->format('d M H:i') }}</td>
                                        <td class="py-2.5 px-3 text-zinc-200">{{ $log->user?->name ?? 'System' }}</td>
                                        <td class="py-2.5 px-3">
                                            <span class="badge px-2 py-0.5 bg-zinc-950 border-zinc-800 font-mono text-red-300">{{ $log->action }}</span>
                                        </td>
                                        <td class="py-2.5 px-3 text-zinc-400 font-mono max-w-xs truncate">{{ $log->metadata ? json_encode($log->metadata) : '—' }}</td>
                                        <td class="py-2.5 pl-3 text-right text-zinc-500 font-mono">{{ $log->ip_address }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
            </div>
        @endif
    </main>

    <!-- ==================== MODAL: OTP VERIFICATION ==================== -->
    <div x-show="otpModalOpen" x-cloak
         class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         role="dialog"
         aria-modal="true"
         aria-labelledby="otp-modal-title">
        <div @click.outside="otpModalOpen = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="w-full max-w-md surface-card p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-red-400">Security Check</span>
                    <h3 id="otp-modal-title" class="text-lg font-bold text-white tracking-tight">Enter Verification Code</h3>
                </div>
                <button @click="otpModalOpen = false"
                        aria-label="Close verification dialog"
                        class="btn btn-ghost p-1 min-h-[44px] min-w-[44px]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <p class="text-xs text-zinc-400" x-text="otpStatusMessage || 'We sent a 6-digit code to your email address.'"></p>

            <template x-if="demoOtpCode">
                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-200 flex items-center justify-between">
                    <span>Demo code: <strong class="font-mono text-sm text-white" x-text="demoOtpCode"></strong></span>
                    <button @click="otpCode = demoOtpCode" type="button" class="underline text-amber-300 font-semibold min-h-[44px]">Auto-fill</button>
                </div>
            </template>

            <template x-if="otpError">
                <div class="p-3 rounded-xl bg-rose-950/70 border border-rose-800 text-xs text-rose-200 flex items-center gap-2" role="alert">
                    <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span x-text="otpError"></span>
                </div>
            </template>

            <form @submit.prevent="verifyOtpAndReveal()" class="space-y-4">
                <label for="otp-code-input" class="sr-only">Verification code</label>
                <input type="text" id="otp-code-input" x-model="otpCode" maxlength="6" placeholder="000000" required autofocus
                       autocomplete="one-time-code" inputmode="numeric"
                       class="input text-center tracking-[0.6em] font-mono text-2xl font-bold py-3.5">
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="otpModalOpen = false"
                            class="btn btn-secondary px-4 py-2.5 text-xs min-h-[44px]">
                        Cancel
                    </button>
                    <button type="submit" :disabled="otpLoading"
                            class="btn btn-primary px-5 py-2.5 text-xs min-h-[44px]">
                        <span x-text="otpLoading ? 'Verifying...' : 'Verify &amp; Reveal'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: DUITNOW QR & RECEIPT UPLOAD ==================== -->
    <div x-show="paymentModalOpen" x-cloak
         class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         role="dialog"
         aria-modal="true"
         aria-labelledby="payment-modal-title">
        <div @click.outside="paymentModalOpen = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="w-full max-w-lg surface-card p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto overscroll-contain">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-pink-400">DuitNow Payment</span>
                    <h3 id="payment-modal-title" class="text-lg font-bold text-white tracking-tight">Scan &amp; Pay</h3>
                </div>
                <button @click="paymentModalOpen = false"
                        aria-label="Close payment dialog"
                        class="btn btn-ghost p-1 min-h-[44px] min-w-[44px]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- DuitNow QR -->
            <div class="p-4 rounded-2xl surface-inner flex flex-col sm:flex-row items-center gap-4">
                <div class="w-40 h-40 rounded-xl bg-white p-2.5 flex items-center justify-center shrink-0">
                    @if ($settings->duitnow_qr_path)
                        <img src="{{ asset('storage/' . $settings->duitnow_qr_path) }}" alt="DuitNow QR code for payment" class="w-full h-full object-contain">
                    @else
                        <svg viewBox="0 0 120 120" class="w-full h-full" aria-hidden="true">
                            <rect width="120" height="120" rx="8" fill="#ffffff"/>
                            <rect x="8" y="8" width="32" height="32" fill="none" stroke="#e11d48" stroke-width="6"/>
                            <rect x="16" y="16" width="16" height="16" fill="#e11d48"/>
                            <rect x="80" y="8" width="32" height="32" fill="none" stroke="#e11d48" stroke-width="6"/>
                            <rect x="88" y="16" width="16" height="16" fill="#e11d48"/>
                            <rect x="8" y="80" width="32" height="32" fill="none" stroke="#e11d48" stroke-width="6"/>
                            <rect x="16" y="88" width="16" height="16" fill="#e11d48"/>
                            <rect x="48" y="12" width="8" height="8" fill="#18181b"/>
                            <rect x="62" y="20" width="8" height="16" fill="#18181b"/>
                            <rect x="46" y="46" width="28" height="28" rx="6" fill="#e11d48"/>
                            <text x="60" y="64" text-anchor="middle" fill="#ffffff" font-size="9" font-weight="bold" font-family="sans-serif">DuitNow</text>
                            <rect x="14" y="48" width="20" height="8" fill="#18181b"/>
                            <rect x="82" y="52" width="24" height="8" fill="#18181b"/>
                            <rect x="48" y="84" width="12" height="24" fill="#18181b"/>
                            <rect x="68" y="80" width="16" height="12" fill="#18181b"/>
                            <rect x="90" y="90" width="18" height="18" fill="#18181b"/>
                        </svg>
                    @endif
                </div>

                <div class="space-y-2.5 text-xs">
                    <div class="inline-flex px-2.5 py-1 rounded-lg bg-pink-500/15 text-pink-300 border border-pink-500/30 font-semibold">
                        Scan with any banking app
                    </div>
                    <div class="text-zinc-300 space-y-0.5">
                        <div>Bank: <strong class="text-white">{{ $settings->bank_name }}</strong></div>
                        <div>Account: <strong class="font-mono text-white">{{ $settings->bank_account_number }}</strong></div>
                        <div>Recipient: <strong class="text-white">{{ $settings->bank_account_holder }}</strong></div>
                    </div>
                    <div class="text-emerald-400 font-bold text-base pt-1 tabular-nums">
                        RM {{ number_format((float) ($myPayment?->amount ?? $user->monthly_share_amount), 2) }}
                    </div>
                </div>
            </div>

            @if ($myPayment)
                <form method="POST" action="{{ route('payments.upload-receipt', $myPayment) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label for="payment-reference" class="block text-xs font-medium text-zinc-300 mb-1.5">Transfer Reference <span class="text-zinc-600">(optional)</span></label>
                        <input type="text" name="reference_note" id="payment-reference" placeholder="e.g. DuitNow Ref #849201"
                               value="{{ old('reference_note', $myPayment->reference_note) }}"
                               class="input">
                    </div>

                    <div>
                        <label for="payment-receipt" class="block text-xs font-medium text-zinc-300 mb-1.5">Upload Receipt</label>
                        <div class="relative">
                            <input type="file" name="receipt" id="payment-receipt" required accept=".jpg,.jpeg,.png,.pdf"
                                   class="w-full p-3.5 rounded-xl bg-zinc-950 border border-dashed border-zinc-700 hover:border-zinc-600 text-xs text-zinc-300 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:bg-red-600 file:text-white file:font-semibold file:cursor-pointer transition">
                        </div>
                        <p class="text-[11px] text-zinc-500 mt-1.5">JPG, PNG, or PDF up to 4MB</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="paymentModalOpen = false"
                                class="btn btn-secondary px-4 py-2.5 text-xs min-h-[44px]">
                            Close
                        </button>
                        <button type="submit"
                                class="btn btn-primary px-5 py-2.5 text-xs min-h-[44px]">
                            Submit receipt
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function householdPortal(config) {
            return {
                imapLoading: false,
                imapMode: null,
                imapError: null,
                imapItems: [],
                countdownTimer: null,

                masterEmail: config.masterEmail,
                profileName: config.profileName,
                profilePin: config.profilePin,
                pinVisible: false,

                isStepUpVerified: config.isStepUpVerified,
                isOverdueLocked: config.isOverdueLocked,
                revealedPassword: null,
                remaskCountdown: 0,
                remaskInterval: null,

                otpModalOpen: false,
                otpLoading: false,
                otpCode: '',
                otpStatusMessage: '',
                otpError: '',
                demoOtpCode: null,

                paymentModalOpen: false,
                adminDrawerOpen: config.initialAdminOpen || false,
                adminTab: config.initialAdminTab || 'vault',
                toastMessage: '',

                openAdminPanel(tab = null) {
                    if (tab) {
                        this.adminTab = tab;
                    }
                    this.adminDrawerOpen = true;
                },

                init() {
                    this.fetchVerificationEmails();
                    this.countdownTimer = setInterval(() => {
                        this.imapItems = this.imapItems.map(item => ({
                            ...item,
                            remaining_seconds: Math.max(0, item.remaining_seconds - 1)
                        }));
                    }, 1000);
                },

                async fetchVerificationEmails() {
                    this.imapLoading = true;
                    this.imapError = null;
                    try {
                        const res = await fetch(config.imapFetchUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        this.imapMode = data.mode || 'live';
                        this.imapItems = data.items || [];
                        if (data.error) {
                            this.imapError = data.error;
                        }
                    } catch (e) {
                        this.imapError = 'Could not reach the server. Please try again.';
                    } finally {
                        this.imapLoading = false;
                    }
                },

                async initiateVaultReveal() {
                    this.otpError = '';
                    if (this.isStepUpVerified) {
                        await this.verifyOtpAndReveal();
                        return;
                    }

                    this.otpLoading = true;
                    try {
                        const res = await fetch(config.otpRequestUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();

                        if (res.status === 402) {
                            this.isOverdueLocked = true;
                            this.showToast(data.message || 'Payment required to access credentials.');
                            return;
                        }

                        if (!res.ok) {
                            this.showToast(data.message || 'Could not send verification code.');
                            return;
                        }

                        this.otpStatusMessage = data.message;
                        this.demoOtpCode = data.demo_otp || null;
                        this.otpModalOpen = true;
                    } finally {
                        this.otpLoading = false;
                    }
                },

                async verifyOtpAndReveal() {
                    this.otpLoading = true;
                    this.otpError = '';
                    try {
                        const res = await fetch(config.vaultRevealUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ otp: this.otpCode })
                        });
                        const data = await res.json();

                        if (!res.ok) {
                            this.otpError = data.message || 'Verification failed. Please try again.';
                            return;
                        }

                        this.isStepUpVerified = true;
                        this.otpModalOpen = false;
                        this.revealedPassword = data.password;
                        this.profilePin = data.profile_pin || this.profilePin;
                        this.startRemaskTimer(data.remask_after_seconds || 15);
                        this.showToast('Password revealed — auto-hides in 15 seconds.');
                    } finally {
                        this.otpLoading = false;
                    }
                },

                startRemaskTimer(seconds) {
                    if (this.remaskInterval) clearInterval(this.remaskInterval);
                    this.remaskCountdown = seconds;
                    this.remaskInterval = setInterval(() => {
                        this.remaskCountdown--;
                        if (this.remaskCountdown <= 0) {
                            this.revealedPassword = null;
                            clearInterval(this.remaskInterval);
                        }
                    }, 1000);
                },

                async triggerGraceBypass() {
                    const res = await fetch(config.graceBypassUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': config.csrfToken,
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (res.ok) {
                        this.isOverdueLocked = false;
                        this.showToast(data.message);
                    } else {
                        this.showToast(data.message || 'Emergency access is not available.');
                    }
                },

                formatCountdown(seconds) {
                    if (seconds <= 0) return 'Expired';
                    const m = Math.floor(seconds / 60);
                    const s = seconds % 60;
                    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                },

                copyText(value, message) {
                    navigator.clipboard.writeText(value);
                    this.showToast(message || 'Copied!');
                },

                showToast(msg) {
                    this.toastMessage = msg;
                    const liveRegion = document.getElementById('live-region');
                    if (liveRegion) {
                        liveRegion.textContent = msg;
                    }
                    setTimeout(() => {
                        if (this.toastMessage === msg) this.toastMessage = '';
                    }, 3200);
                }
            };
        }
    </script>
@endpush
