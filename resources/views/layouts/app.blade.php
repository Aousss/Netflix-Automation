<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Netflix Household Hub')</title>

    {{-- Fonts: Inter (UI) + JetBrains Mono (code) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
                        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
                    },
                }
            }
        }
    </script>

    {{-- Design system --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @stack('head')
</head>
<body class="antialiased font-sans bg-zinc-950 text-zinc-100 min-h-dvh @yield('body-class')"
      @yield('body-attrs')>

    {{-- Skip link (accessibility) --}}
    <a href="#main-content" class="skip-link">Skip to main content</a>

    {{-- Ambient glow (decorative, hidden from assistive tech) --}}
    <div class="ambient-glow" aria-hidden="true"></div>

    {{-- Live region for toast/status announcements --}}
    <div id="live-region" role="status" aria-live="polite" aria-atomic="true" class="sr-only"></div>

    @yield('content')

    {{-- Footer --}}
    <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-center text-xs text-zinc-600">
        <span>Netflix Household Hub</span> &middot; <span>Private use only</span>
    </footer>

    @stack('scripts')
</body>
</html>
