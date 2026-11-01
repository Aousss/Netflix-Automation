<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Netflix Household Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeInUp 0.6s ease-out both; }
        .fade-in-d1 { animation: fadeInUp 0.6s ease-out 0.15s both; }
        .fade-in-d2 { animation: fadeInUp 0.6s ease-out 0.3s both; }
        .fade-in-d3 { animation: fadeInUp 0.6s ease-out 0.45s both; }
    </style>
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen flex flex-col selection:bg-red-600 selection:text-white">

    <!-- Ambient glow -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-red-600/12 blur-[150px] rounded-full"></div>
        <div class="absolute bottom-0 right-1/4 w-[400px] h-[250px] bg-red-600/8 blur-[120px] rounded-full"></div>
    </div>

    <!-- Main content -->
    <div class="flex-1 flex flex-col items-center justify-center p-6 sm:p-8 relative">
        <div class="w-full max-w-2xl text-center space-y-8">

            <!-- Logo -->
            <div class="fade-in">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-red-600 flex items-center justify-center font-black text-white text-3xl shadow-2xl shadow-red-600/30">
                    N
                </div>
            </div>

            <!-- Heading -->
            <div class="space-y-3 fade-in-d1">
                <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-white">
                    <span class="text-red-600">Netflix</span> Household Hub
                </h1>
                <p class="text-base sm:text-lg text-zinc-400 max-w-lg mx-auto leading-relaxed">
                    Your private portal for TV verification codes, shared credentials, and monthly split-billing.
                </p>
            </div>

            <!-- Feature highlights -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-left fade-in-d2">
                <div class="p-4 rounded-2xl bg-zinc-900/80 border border-zinc-800">
                    <div class="w-9 h-9 rounded-xl bg-red-600/15 border border-red-600/20 flex items-center justify-center mb-3">
                        <svg class="w-4.5 h-4.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-white">Verification Codes</h3>
                    <p class="text-xs text-zinc-500 mt-1">Fetch Netflix TV codes instantly from your inbox.</p>
                </div>

                <div class="p-4 rounded-2xl bg-zinc-900/80 border border-zinc-800">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-500/20 flex items-center justify-center mb-3">
                        <svg class="w-4.5 h-4.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-white">Secure Vault</h3>
                    <p class="text-xs text-zinc-500 mt-1">Encrypted credentials with email verification.</p>
                </div>

                <div class="p-4 rounded-2xl bg-zinc-900/80 border border-zinc-800">
                    <div class="w-9 h-9 rounded-xl bg-pink-500/15 border border-pink-500/20 flex items-center justify-center mb-3">
                        <svg class="w-4.5 h-4.5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-white">Split Billing</h3>
                    <p class="text-xs text-zinc-500 mt-1">DuitNow payments and transparent ledger.</p>
                </div>
            </div>

            <!-- CTA -->
            <div class="space-y-3 fade-in-d3">
                @auth
                    <a href="{{ url('/dashboard') }}"
                       class="inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-red-600 hover:bg-red-500 text-white font-semibold text-sm shadow-xl shadow-red-600/25 transition">
                        Go to Dashboard
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-red-600 hover:bg-red-500 text-white font-semibold text-sm shadow-xl shadow-red-600/25 transition">
                        Sign In
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                @endauth
                <p class="text-xs text-zinc-600">Private household members only</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4 text-center text-[11px] text-zinc-600">
        Netflix Household Hub &bull; Private use only
    </footer>

</body>
</html>
