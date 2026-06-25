<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SH Customer's Analytics</title>
    <link rel="icon" type="image/svg+xml" href="/sh-logo.svg">
    <link rel="shortcut icon" href="/sh-logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="antialiased min-h-screen" style="background:#F1F5F9;">

<div class="min-h-screen flex">

    {{-- Left panel — dark brand --}}
    <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 relative overflow-hidden" style="background:#0F172A;">

        {{-- Subtle grid --}}
        <div class="absolute inset-0 opacity-5" style="background-image:linear-gradient(rgba(245,166,35,.4) 1px,transparent 1px),linear-gradient(90deg,rgba(245,166,35,.4) 1px,transparent 1px);background-size:48px 48px;"></div>

        {{-- Gold glow orbs --}}
        <div class="absolute top-24 right-12 w-48 h-48 rounded-full blur-3xl" style="background:rgba(245,166,35,0.12);"></div>
        <div class="absolute bottom-24 left-8 w-64 h-64 rounded-full blur-3xl" style="background:rgba(245,166,35,0.06);"></div>

        {{-- Logo --}}
        <div class="relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center font-bold text-base font-display" style="background:#F5A623; color:#0F172A; letter-spacing:0.05em;">SH</div>
                <div>
                    <div class="font-bold tracking-widest uppercase text-sm font-display" style="color:#F5A623; letter-spacing:0.2em;">SELLER'S HUB</div>
                    <div class="text-xs" style="color:#475569;">Customer's Analytics Platform</div>
                </div>
            </div>
        </div>

        {{-- Center hero --}}
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold mb-6" style="background:rgba(245,166,35,0.15); color:#F5A623; border:1px solid rgba(245,166,35,0.3);">
                <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:#F5A623;"></span>
                Live Analytics Dashboard
            </div>

            <h1 class="text-4xl font-bold leading-tight mb-4 font-display" style="color:#F8FAFC;">
                Know your<br>
                customers.<br>
                <span style="color:#F5A623;">Grow your sales.</span>
            </h1>
            <p class="text-sm leading-relaxed mb-8 max-w-sm" style="color:#64748B;">
                Connect your Pancake POS and get real-time insights on your customers — age groups, gender, location, product performance, and returns.
            </p>

            {{-- Features --}}
            <div class="space-y-3">
                @foreach([
                    ['path' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10', 'text' => 'Live KPI dashboard — revenue, orders, AOV'],
                    ['path' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'text' => 'Customer analytics — age, gender, behavior'],
                    ['path' => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6', 'text' => 'RTS & returns by courier and province'],
                    ['path' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7', 'text' => 'Philippine province heatmap'],
                ] as $f)
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background:rgba(245,166,35,0.15);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#F5A623;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['path'] }}"/>
                        </svg>
                    </div>
                    <span class="text-sm" style="color:#94A3B8;">{{ $f['text'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Footer --}}
        <div class="relative z-10">
            <p class="text-xs" style="color:#334155;">Powered by Pancake POS API · Seller's Hub © 2026</p>
        </div>
    </div>

    {{-- Right panel — form --}}
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-6 sm:px-12 lg:px-16 py-12 bg-white">

        {{-- Mobile logo --}}
        <div class="lg:hidden flex items-center gap-2.5 mb-8">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-sm font-display" style="background:#F5A623; color:#0F172A;">SH</div>
            <div>
                <div class="font-bold text-xs tracking-widest uppercase font-display" style="color:#0F172A; letter-spacing:0.15em;">SELLER'S HUB</div>
                <div class="text-xs text-gray-400">Customer's Analytics</div>
            </div>
        </div>

        <div class="w-full max-w-md mx-auto">
            {{ $slot }}
        </div>
    </div>
</div>

</body>
</html>
