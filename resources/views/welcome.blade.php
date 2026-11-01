@extends('layouts.app')

@section('title', 'Netflix Household Hub')

@section('content')
<main id="main-content" class="flex-1 flex flex-col items-center justify-center p-6 sm:p-8 relative z-10">
    <div class="w-full max-w-2xl text-center space-y-10">

        {{-- Logo --}}
        <div class="animate-enter flex justify-center">
            <div class="w-16 h-16 rounded-2xl bg-red-600 flex items-center justify-center font-black text-white text-3xl shadow-2xl shadow-red-600/30"
                 aria-hidden="true">
                N
            </div>
        </div>

        {{-- Heading --}}
        <div class="space-y-3 animate-enter-d1">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-white"
                style="text-wrap: balance">
                <span class="text-red-600">Netflix</span> Household Hub
            </h1>
            <p class="text-base sm:text-lg text-zinc-400 max-w-lg mx-auto leading-relaxed"
               style="max-width: 65ch">
                Your private portal for TV verification codes, shared credentials, and monthly split-billing.
            </p>
        </div>

        {{-- Feature highlights --}}
        <ul role="list" class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-left animate-enter-d2">

            {{-- Verification Codes --}}
            <li class="surface-card p-4">
                <div class="w-9 h-9 rounded-xl bg-red-600/15 border border-red-600/20 flex items-center justify-center mb-3">
                    <svg class="w-[18px] h-[18px] text-red-400" fill="none" stroke="currentColor"
                         stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h2 class="text-sm font-semibold text-white tracking-tight">Verification Codes</h2>
                <p class="text-xs text-zinc-500 mt-1 leading-relaxed">
                    Fetch Netflix TV codes instantly from your inbox.
                </p>
            </li>

            {{-- Secure Vault --}}
            <li class="surface-card p-4">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-500/20 flex items-center justify-center mb-3">
                    <svg class="w-[18px] h-[18px] text-emerald-400" fill="none" stroke="currentColor"
                         stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h2 class="text-sm font-semibold text-white tracking-tight">Secure Vault</h2>
                <p class="text-xs text-zinc-500 mt-1 leading-relaxed">
                    Encrypted credentials with email verification.
                </p>
            </li>

            {{-- Split Billing --}}
            <li class="surface-card p-4">
                <div class="w-9 h-9 rounded-xl bg-pink-500/15 border border-pink-500/20 flex items-center justify-center mb-3">
                    <svg class="w-[18px] h-[18px] text-pink-400" fill="none" stroke="currentColor"
                         stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h2 class="text-sm font-semibold text-white tracking-tight">Split Billing</h2>
                <p class="text-xs text-zinc-500 mt-1 leading-relaxed">
                    DuitNow payments and transparent ledger.
                </p>
            </li>

        </ul>

        {{-- CTA --}}
        <div class="space-y-3 animate-enter-d3">
            @auth
                <a href="{{ url('/dashboard') }}"
                   class="btn btn-primary px-8 min-h-[44px]">
                    Go to dashboard
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                         stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="btn btn-primary px-8 min-h-[44px]">
                    Sign in
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                         stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            @endauth

            <p class="text-xs text-zinc-600">Private household members only</p>
        </div>

    </div>
</main>
@endsection
