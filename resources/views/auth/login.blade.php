@extends('layouts.app')

@section('title', 'Sign In — Netflix Household Hub')

@section('body-class', 'flex flex-col selection:bg-red-600 selection:text-white')

@push('head')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
<main id="main-content" class="flex-1 flex flex-col justify-center items-center p-4 sm:p-6">
    <div class="relative w-full max-w-4xl space-y-6">

        {{-- Brand Header --}}
        <header class="text-center space-y-3 animate-enter" role="banner">
            <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-zinc-900 border border-zinc-800 text-xs font-medium text-zinc-400">
                <span class="w-2 h-2 rounded-full bg-red-600 animate-pulse" aria-hidden="true"></span>
                Private Household Portal
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white" style="text-wrap: balance">
                <span class="text-red-600">NETFLIX</span> HOUSEHOLD HUB
            </h1>
            <p class="text-sm text-zinc-400 max-w-md mx-auto" style="text-wrap: balance">
                Your shared space for TV verification codes, account credentials &amp; monthly split-billing.
            </p>
        </header>

        {{-- DB Error Alert --}}
        @if (! empty($dbError))
            <div role="alert"
                 class="animate-enter-d1 p-4 rounded-xl bg-amber-950/70 border border-amber-700/80 text-amber-100 text-xs space-y-1.5">
                <div class="font-bold text-amber-300 flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    <span>Database Connection Notice — Check Laravel Cloud Environment Variables</span>
                </div>
                <p class="font-mono text-[11px] text-amber-200/90 break-all">{{ $dbError }}</p>
            </div>
        @endif

        {{-- Session Error Alert --}}
        @if (session('error'))
            <div role="alert"
                 class="animate-enter-d1 p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-200 text-sm flex items-center gap-2.5">
                <svg class="w-5 h-5 shrink-0 text-rose-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Session Status Alert (magic link sent, etc.) --}}
        @if (session('status'))
            <div role="status"
                 class="animate-enter-d1 p-4 rounded-xl bg-emerald-950/60 border border-emerald-800/80 text-emerald-200 text-sm flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
                @if (session('magic_link_preview'))
                    <a href="{{ session('magic_link_preview') }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition min-h-[44px]">
                        Open Sign-In Link
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </a>
                @endif
            </div>
        @endif

        @php
            $showQuickAccess = app()->environment('local')
                || config('app.debug')
                || $whitelistedUsers->contains(fn ($u) => $u->email === 'admin@household.local');
        @endphp

        {{-- Two-column grid: Quick Access + Sign In card --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start animate-enter-d2">

            {{-- Left Column: Quick Household Member Access (dev/demo/initial setup only) --}}
            @if ($showQuickAccess)
                <section class="lg:col-span-7 surface-card p-6 shadow-2xl space-y-4" aria-labelledby="quick-access-heading">
                    <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-amber-400">Quick Access</p>
                            <h2 id="quick-access-heading" class="text-lg font-bold text-white">Choose Your Profile</h2>
                        </div>
                        <span class="badge bg-zinc-950 border-zinc-800 text-zinc-400">
                            {{ count($whitelistedUsers) }} Members
                        </span>
                    </div>

                    <ul class="space-y-2.5" role="list">
                        @foreach ($whitelistedUsers as $u)
                            <li>
                                <form method="POST" action="{{ route('auth.demo.switch', $u) }}">
                                    @csrf
                                    <button type="submit"
                                            class="w-full flex items-center justify-between px-4 py-3 rounded-xl surface-inner hover:border-red-600/50 hover:bg-zinc-800/90 text-left transition group min-h-[44px]">
                                        <div class="flex items-center gap-3.5">
                                            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 {{ $u->isAdmin() ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'bg-zinc-800 text-zinc-200' }}"
                                                 aria-hidden="true">
                                                {{ strtoupper(substr($u->netflix_profile_name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-zinc-100 group-hover:text-white flex items-center gap-2 flex-wrap">
                                                    <span>{{ $u->name }}</span>
                                                    @if ($u->isAdmin())
                                                        <span class="badge bg-red-600/20 text-red-400 border-red-600/30">Admin</span>
                                                    @else
                                                        <span class="badge bg-zinc-900 text-zinc-400 border-zinc-800">Member</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-zinc-500 mt-0.5">{{ $u->email }}</div>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1.5 rounded-lg bg-red-600/15 group-hover:bg-red-600 text-red-400 group-hover:text-white text-xs font-semibold transition shrink-0"
                                              aria-hidden="true">
                                            Enter &rarr;
                                        </span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Right Column: Google OAuth + Magic Link --}}
            <section class="{{ $showQuickAccess ? 'lg:col-span-5' : 'lg:col-span-6 lg:col-start-4' }} surface-card p-6 shadow-2xl space-y-6"
                     aria-labelledby="sign-in-heading">

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 id="sign-in-heading" class="text-xs font-bold uppercase tracking-wider text-zinc-400">Sign In</h2>
                        @if (! empty($hasFirebase))
                            <span class="badge bg-emerald-950/80 border-emerald-800/70 text-emerald-300">
                                Firebase Auth Ready
                            </span>
                        @endif
                    </div>

                    {{-- Firebase auth error (shown via JS) --}}
                    <div id="firebase-auth-error"
                         role="alert"
                         aria-live="assertive"
                         class="hidden p-3 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-200 text-xs"></div>

                    @if (! empty($hasFirebase))
                        {{-- Hidden form: Firebase posts the id_token here --}}
                        <form id="firebase-login-form" method="POST" action="{{ route('auth.firebase.callback') }}" class="hidden">
                            @csrf
                            <input type="hidden" name="id_token" id="firebase-id-token">
                        </form>

                        <button type="button" id="firebase-google-btn"
                                class="btn w-full justify-center gap-3 bg-white hover:bg-zinc-100 text-zinc-900 min-h-[44px] py-3 rounded-xl shadow font-semibold text-sm transition disabled:opacity-60">
                            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="#EA4335" d="M12 5c1.6 0 3 .6 4.1 1.7l3.1-3.1C17.3 1.8 14.8 1 12 1 7.4 1 3.5 3.6 1.6 7.4l3.7 2.8C6.2 7.2 8.9 5 12 5z"/>
                                <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.8-2.4 3.6l3.7 2.9c2.2-2 3.7-5 3.7-8.7z"/>
                                <path fill="#FBBC05" d="M5.3 14.8c-.2-.8-.4-1.6-.4-2.5s.2-1.7.4-2.5L1.6 7C.6 9 0 11.2 0 13.5s.6 4.5 1.6 6.5l3.7-2.9z"/>
                                <path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3.1 0-5.8-2.1-6.7-5l-3.7 2.8C3.5 21.4 7.4 24 12 24z"/>
                            </svg>
                            <span id="firebase-google-btn-text">Continue with Google</span>
                        </button>
                    @else
                        <a href="{{ route('auth.google') }}"
                           class="btn w-full justify-center gap-3 bg-white hover:bg-zinc-100 text-zinc-900 min-h-[44px] py-3 rounded-xl shadow font-semibold text-sm transition">
                            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="#EA4335" d="M12 5c1.6 0 3 .6 4.1 1.7l3.1-3.1C17.3 1.8 14.8 1 12 1 7.4 1 3.5 3.6 1.6 7.4l3.7 2.8C6.2 7.2 8.9 5 12 5z"/>
                                <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.8-2.4 3.6l3.7 2.9c2.2-2 3.7-5 3.7-8.7z"/>
                                <path fill="#FBBC05" d="M5.3 14.8c-.2-.8-.4-1.6-.4-2.5s.2-1.7.4-2.5L1.6 7C.6 9 0 11.2 0 13.5s.6 4.5 1.6 6.5l3.7-2.9z"/>
                                <path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3.1 0-5.8-2.1-6.7-5l-3.7 2.8C3.5 21.4 7.4 24 12 24z"/>
                            </svg>
                            Continue with Google
                        </a>
                    @endif

                    <p class="text-[11px] text-center text-zinc-500">
                        Only registered household members can sign in.
                    </p>
                </div>

                {{-- Divider --}}
                <div class="relative flex py-1 items-center" aria-hidden="true">
                    <div class="flex-grow border-t border-zinc-800"></div>
                    <span class="flex-shrink mx-3 text-xs uppercase tracking-wider text-zinc-500">Or use email</span>
                    <div class="flex-grow border-t border-zinc-800"></div>
                </div>

                {{-- Passwordless / Magic Link email form --}}
                <form method="POST" action="{{ route('auth.magic.send') }}"
                      class="space-y-3"
                      x-data="{ submitting: false }"
                      @submit="submitting = true">
                    @csrf

                    <div>
                        <label for="email" class="block text-xs font-medium text-zinc-400 mb-1.5">
                            Email address
                            <span class="text-red-500 ml-0.5" aria-hidden="true">*</span>
                            <span class="sr-only">(required)</span>
                        </label>
                        <input type="email"
                               id="email"
                               name="email"
                               required
                               autocomplete="email"
                               spellcheck="false"
                               placeholder="you@example.com"
                               value="{{ old('email', (app()->environment('local') || config('app.debug')) ? 'admin@household.local' : '') }}"
                               class="input"
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>

                        @error('email')
                            <p id="email-error" role="alert" class="text-xs text-rose-400 mt-1.5 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.051 3.378c.866-1.5 3.032-1.5 3.898 0l7.354 12.748zM12 15.75h.007v.008H12v-.008z"/>
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="btn btn-primary w-full min-h-[44px] py-2.5 rounded-xl text-sm"
                            :aria-disabled="submitting"
                            :class="{ 'opacity-60 cursor-not-allowed': submitting }">
                        <span x-show="!submitting">Send Sign-In Link</span>
                        <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Sending link…
                        </span>
                    </button>

                    <p class="text-[11px] text-center text-zinc-500">
                        We'll email you a secure one-time link. No password needed.
                    </p>
                </form>

            </section>
        </div>
    </div>
</main>
@endsection

@push('scripts')
    @if (! empty($hasFirebase))
        <script type="module">
            import { initializeApp } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js";
            import { getAuth, GoogleAuthProvider, signInWithPopup, signInWithRedirect, getRedirectResult } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js";

            const firebaseConfig = {
                apiKey:            @json($firebaseConfig['api_key']),
                authDomain:        @json($firebaseConfig['auth_domain']),
                projectId:         @json($firebaseConfig['project_id']),
                storageBucket:     @json($firebaseConfig['storage_bucket']),
                messagingSenderId: @json($firebaseConfig['messaging_sender_id']),
                appId:             @json($firebaseConfig['app_id']),
                measurementId:     @json($firebaseConfig['measurement_id']),
            };

            const app      = initializeApp(firebaseConfig);
            const auth     = getAuth(app);
            const provider = new GoogleAuthProvider();
            provider.setCustomParameters({ prompt: 'select_account' });

            const btn       = document.getElementById('firebase-google-btn');
            const btnText   = document.getElementById('firebase-google-btn-text');
            const errBox    = document.getElementById('firebase-auth-error');
            const form      = document.getElementById('firebase-login-form');
            const tokenInput = document.getElementById('firebase-id-token');

            function showError(msg) {
                errBox.textContent = msg;
                errBox.classList.remove('hidden');
                btn.disabled = false;
                btn.removeAttribute('aria-disabled');
                btnText.textContent = 'Continue with Google';
            }

            async function submitFirebaseUser(user) {
                btn.disabled = true;
                btn.setAttribute('aria-disabled', 'true');
                btnText.textContent = 'Verifying household access…';
                const idToken = await user.getIdToken(true);
                tokenInput.value = idToken;
                form.submit();
            }

            // Handle redirect result if popup was blocked on a prior attempt
            getRedirectResult(auth)
                .then((result) => {
                    if (result && result.user) {
                        submitFirebaseUser(result.user);
                    }
                })
                .catch((error) => {
                    showError(error.message || 'Google Sign-In failed. Please try again.');
                });

            btn?.addEventListener('click', async () => {
                errBox.classList.add('hidden');
                btn.disabled = true;
                btn.setAttribute('aria-disabled', 'true');
                btnText.textContent = 'Opening Google Sign-In…';

                try {
                    const result = await signInWithPopup(auth, provider);
                    await submitFirebaseUser(result.user);
                } catch (error) {
                    if (error.code === 'auth/popup-blocked') {
                        await signInWithRedirect(auth, provider);
                        return;
                    }
                    if (error.code === 'auth/unauthorized-domain') {
                        showError(`Domain "${window.location.hostname}" is not authorized in Firebase. Add it in Firebase Console → Authentication → Settings → Authorized domains.`);
                        return;
                    }
                    if (error.code === 'auth/operation-not-allowed') {
                        showError('Google Sign-In is not enabled yet. Enable it in Firebase Console → Authentication → Sign-in method → Google.');
                        return;
                    }
                    showError(error.message || 'Google Sign-In was cancelled or failed. Please try again.');
                }
            });
        </script>
    @endif
@endpush
