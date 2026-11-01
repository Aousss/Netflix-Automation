<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — Netflix Household Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out both; }
        .fade-in-up-delay { animation: fadeInUp 0.5s ease-out 0.15s both; }
    </style>
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen flex flex-col selection:bg-red-600 selection:text-white">

    <!-- Ambient glow -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[650px] h-[350px] bg-red-600/15 blur-[130px] rounded-full"></div>
    </div>

    <!-- Main content area -->
    <div class="flex-1 flex flex-col justify-center items-center p-4 sm:p-6">
        <div class="relative w-full max-w-4xl space-y-6">
            <!-- Brand Header -->
            <div class="text-center space-y-3 fade-in-up">
                <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-zinc-900 border border-zinc-800 text-xs font-medium text-zinc-400">
                    <span class="w-2 h-2 rounded-full bg-red-600 animate-pulse"></span>
                    Private Household Portal
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                    <span class="text-red-600">NETFLIX</span> HOUSEHOLD HUB
                </h1>
                <p class="text-sm text-zinc-400 max-w-md mx-auto">
                    Your shared space for TV verification codes, account credentials &amp; monthly split-billing.
                </p>
            </div>

            @if (! empty($dbError))
                <div class="p-4 rounded-xl bg-amber-950/70 border border-amber-700/80 text-amber-100 text-xs space-y-1.5">
                    <div class="font-bold text-amber-300 flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 shrink-0 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <span>Database Connection Notice (Check Laravel Cloud Environment Variables)</span>
                    </div>
                    <p class="font-mono text-[11px] text-amber-200/90 break-all">{{ $dbError }}</p>
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-200 text-sm flex items-center gap-2.5">
                    <svg class="w-5 h-5 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            @if (session('status'))
                <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-800/80 text-emerald-200 text-sm flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <svg class="w-5 h-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                    @if (session('magic_link_preview'))
                        <a href="{{ session('magic_link_preview') }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition shadow">
                            Open Sign-In Link &rarr;
                        </a>
                    @endif
                </div>
            @endif

            @php
                $showQuickAccess = app()->environment('local')
                    || config('app.debug')
                    || $whitelistedUsers->contains(fn ($u) => $u->email === 'admin@household.local');
            @endphp
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start fade-in-up-delay">
                <!-- Left Column: Quick Household Member Login (Dev/Demo or Initial Setup) -->
                @if ($showQuickAccess)
                    <div class="lg:col-span-7 bg-zinc-900/85 border border-zinc-800 rounded-2xl p-6 shadow-2xl space-y-4">
                        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-amber-400">Quick Access</span>
                                <h2 class="text-lg font-bold text-white">Choose Your Profile</h2>
                            </div>
                            <span class="px-2.5 py-1 rounded-full bg-zinc-950 border border-zinc-800 text-[11px] text-zinc-400">{{ count($whitelistedUsers) }} Members</span>
                        </div>

                        <div class="grid grid-cols-1 gap-2.5">
                            @foreach ($whitelistedUsers as $u)
                                <form method="POST" action="{{ route('auth.demo.switch', $u) }}">
                                    @csrf
                                    <button type="submit"
                                            class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-zinc-950 hover:bg-zinc-800/90 border border-zinc-800 hover:border-red-600/50 text-left transition group">
                                        <div class="flex items-center gap-3.5">
                                            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs {{ $u->isAdmin() ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'bg-zinc-800 text-zinc-200' }}">
                                                {{ strtoupper(substr($u->netflix_profile_name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-zinc-100 group-hover:text-white flex items-center gap-2">
                                                    <span>{{ $u->name }}</span>
                                                    @if ($u->isAdmin())
                                                        <span class="px-2 py-0.5 text-[10px] rounded-md bg-red-600/20 text-red-400 border border-red-600/30 font-bold">ADMIN</span>
                                                    @else
                                                        <span class="px-2 py-0.5 text-[10px] rounded-md bg-zinc-900 text-zinc-400 border border-zinc-800">MEMBER</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-zinc-500">{{ $u->email }}</div>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1.5 rounded-lg bg-red-600/15 group-hover:bg-red-600 text-red-400 group-hover:text-white text-xs font-semibold transition">
                                            Enter &rarr;
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Right Column: Google OAuth + Magic Link -->
                <div class="{{ $showQuickAccess ? 'lg:col-span-5' : 'lg:col-span-6 lg:col-start-4' }} bg-zinc-900/80 backdrop-blur-xl border border-zinc-800 rounded-2xl p-6 shadow-2xl space-y-6">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Sign In</span>
                            @if (! empty($hasFirebase))
                                <span class="px-2 py-0.5 rounded-full bg-emerald-950/80 border border-emerald-800/70 text-[10px] font-medium text-emerald-300">
                                    Firebase Auth Ready
                                </span>
                            @endif
                        </div>

                        <div id="firebase-auth-error" class="hidden p-3 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-200 text-xs"></div>

                        @if (! empty($hasFirebase))
                            <form id="firebase-login-form" method="POST" action="{{ route('auth.firebase.callback') }}" class="hidden">
                                @csrf
                                <input type="hidden" name="id_token" id="firebase-id-token">
                            </form>

                            <button type="button" id="firebase-google-btn"
                                    class="w-full flex items-center justify-center gap-3 px-4 py-3.5 rounded-xl bg-white hover:bg-zinc-100 text-zinc-900 font-semibold text-sm shadow transition disabled:opacity-60">
                                <svg class="w-5 h-5" viewBox="0 0 24 24">
                                    <path fill="#EA4335" d="M12 5c1.6 0 3 .6 4.1 1.7l3.1-3.1C17.3 1.8 14.8 1 12 1 7.4 1 3.5 3.6 1.6 7.4l3.7 2.8C6.2 7.2 8.9 5 12 5z"/>
                                    <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.8-2.4 3.6l3.7 2.9c2.2-2 3.7-5 3.7-8.7z"/>
                                    <path fill="#FBBC05" d="M5.3 14.8c-.2-.8-.4-1.6-.4-2.5s.2-1.7.4-2.5L1.6 7C.6 9 0 11.2 0 13.5s.6 4.5 1.6 6.5l3.7-2.9z"/>
                                    <path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3.1 0-5.8-2.1-6.7-5l-3.7 2.8C3.5 21.4 7.4 24 12 24z"/>
                                </svg>
                                <span id="firebase-google-btn-text">Continue with Google</span>
                            </button>
                        @else
                            <a href="{{ route('auth.google') }}"
                               class="w-full flex items-center justify-center gap-3 px-4 py-3.5 rounded-xl bg-white hover:bg-zinc-100 text-zinc-900 font-semibold text-sm shadow transition">
                                <svg class="w-5 h-5" viewBox="0 0 24 24">
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

                    <div class="relative flex py-1 items-center">
                        <div class="flex-grow border-t border-zinc-800"></div>
                        <span class="flex-shrink mx-3 text-xs uppercase tracking-wider text-zinc-500">Or use email</span>
                        <div class="flex-grow border-t border-zinc-800"></div>
                    </div>

                    <!-- Passwordless Email Sign-In -->
                    <form method="POST" action="{{ route('auth.magic.send') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="email" class="block text-xs font-medium text-zinc-400 mb-1.5">Your Email Address</label>
                            <input type="email" id="email" name="email" required
                                   placeholder="you@example.com"
                                   value="{{ old('email', (app()->environment('local') || config('app.debug')) ? 'admin@household.local' : '') }}"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:border-red-600 transition">
                            @error('email')
                                <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit"
                                class="w-full py-2.5 px-4 rounded-xl bg-red-600 hover:bg-red-500 text-white font-semibold text-sm transition shadow-lg shadow-red-600/20">
                            Send Sign-In Link
                        </button>
                        <p class="text-[11px] text-center text-zinc-500">
                            We'll email you a secure one-time link. No password needed.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4 text-center text-[11px] text-zinc-600">
        Netflix Household Hub &bull; Private use only
    </footer>

    @if (! empty($hasFirebase))
        <script type="module">
            import { initializeApp } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js";
            import { getAuth, GoogleAuthProvider, signInWithPopup, signInWithRedirect, getRedirectResult } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js";

            const firebaseConfig = {
                apiKey: @json($firebaseConfig['api_key']),
                authDomain: @json($firebaseConfig['auth_domain']),
                projectId: @json($firebaseConfig['project_id']),
                storageBucket: @json($firebaseConfig['storage_bucket']),
                messagingSenderId: @json($firebaseConfig['messaging_sender_id']),
                appId: @json($firebaseConfig['app_id']),
                measurementId: @json($firebaseConfig['measurement_id']),
            };

            const app = initializeApp(firebaseConfig);
            const auth = getAuth(app);
            const provider = new GoogleAuthProvider();
            provider.setCustomParameters({ prompt: 'select_account' });

            const btn = document.getElementById('firebase-google-btn');
            const btnText = document.getElementById('firebase-google-btn-text');
            const errBox = document.getElementById('firebase-auth-error');
            const form = document.getElementById('firebase-login-form');
            const tokenInput = document.getElementById('firebase-id-token');

            function showError(msg) {
                errBox.textContent = msg;
                errBox.classList.remove('hidden');
                btn.disabled = false;
                btnText.textContent = 'Continue with Google';
            }

            async function submitFirebaseUser(user) {
                btn.disabled = true;
                btnText.textContent = 'Verifying household access...';
                const idToken = await user.getIdToken(true);
                tokenInput.value = idToken;
                form.submit();
            }

            // Handle redirect result if popup was blocked
            getRedirectResult(auth)
                .then((result) => {
                    if (result && result.user) {
                        submitFirebaseUser(result.user);
                    }
                })
                .catch((error) => {
                    showError(error.message || 'Google Sign-In failed.');
                });

            btn?.addEventListener('click', async () => {
                errBox.classList.add('hidden');
                btn.disabled = true;
                btnText.textContent = 'Opening Google Sign-In...';

                try {
                    const result = await signInWithPopup(auth, provider);
                    await submitFirebaseUser(result.user);
                } catch (error) {
                    if (error.code === 'auth/popup-blocked') {
                        await signInWithRedirect(auth, provider);
                        return;
                    }
                    if (error.code === 'auth/unauthorized-domain') {
                        showError(`Domain "${window.location.hostname}" is not authorized in Firebase yet. In Firebase Console → Authentication → Settings → Authorized domains, add "${window.location.hostname}".`);
                        return;
                    }
                    if (error.code === 'auth/operation-not-allowed') {
                        showError('Google Sign-In provider is not enabled yet in Firebase Console → Authentication → Sign-in method → Enable Google.');
                        return;
                    }
                    showError(error.message || 'Google Sign-In was cancelled or failed.');
                }
            });
        </script>
    @endif

</body>
</html>
