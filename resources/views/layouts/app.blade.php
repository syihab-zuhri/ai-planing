<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'BlueprintForge' }} — Generator Blueprint Proyek Terstruktur</title>
    <meta name="description" content="BlueprintForge adalah generator blueprint proyek perangkat lunak terstruktur dalam waktu kurang dari 30 menit, mengikuti standar PLANNING_v3.">

    {{-- Inter via Google Fonts (preconnect untuk perf) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Vite: Tailwind (app.css) + Alpine & helpers (app.js) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="min-h-full flex flex-col bg-bg text-text antialiased">

    {{-- ============================================================
         Header
         ============================================================ --}}
    <header class="bg-white border-b border-border" role="banner">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

            {{-- Logo + brand --}}
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-primary hover:text-primary-hover transition-colors" aria-label="BlueprintForge, kembali ke halaman utama">
                <span class="text-2xl" aria-hidden="true">🏗️</span>
                <span class="text-lg">BlueprintForge</span>
            </a>

            {{-- Nav --}}
            <nav class="flex items-center gap-1 sm:gap-2" aria-label="Navigasi utama">
                <a href="{{ url('/archive') }}"
                   class="btn-primary text-xs sm:text-sm px-3 sm:px-4 py-1.5 sm:py-2"
                   @if(request()->is('archive*')) aria-current="page" @endif>
                    📁 Arsip & Unduhan
                </a>
                <a href="{{ url('/about') }}"
                   class="btn-ghost text-xs sm:text-sm px-2.5 sm:px-3 py-1.5 sm:py-2"
                   @if(request()->is('about*')) aria-current="page" @endif>
                    Tentang
                </a>

                {{-- Auto-save indicator slot (opsional, diisi via stack) --}}
                @isset($autoSaveState)
                    <div class="ml-2 px-3 py-1.5 text-xs text-text-muted bg-bg-alt rounded-sm border border-border"
                         role="status"
                         aria-live="polite">
                        {{ $autoSaveState }}
                    </div>
                @endisset
            </nav>
        </div>
    </header>

    {{-- ============================================================
         Main content
         ============================================================ --}}
    <main id="main" class="flex-1" role="main" tabindex="-1">
        @yield('content')
    </main>

    {{-- ============================================================
         Footer
         ============================================================ --}}
    <footer class="bg-bg-alt border-t border-border mt-auto" role="contentinfo">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p class="text-sm text-text-muted">
                &copy; {{ date('Y') }} BlueprintForge. Hak cipta dilindungi.
            </p>
            <p class="text-sm text-text-muted">
                Powered by <span class="font-medium text-primary">PLANNING_v3</span> Standard.
            </p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>