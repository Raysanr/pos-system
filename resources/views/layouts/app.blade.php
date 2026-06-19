<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SH Customer's Analytics — @yield('title', 'Dashboard')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; background: #F1F5F9; }
        .font-display { font-family: 'Space Grotesk', sans-serif; }
        :root {
            --gold: #F5A623;
            --gold-dark: #D4891A;
            --gold-light: #FDF3E0;
            --dark: #0F172A;
            --dark-2: #1E293B;
            --dark-3: #334155;
        }
    </style>
    @stack('head')
</head>
<body class="h-full">
<div class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-60 flex-shrink-0 flex flex-col z-20" style="background:#0F172A;">

        <!-- Logo -->
        <div class="px-5 py-5 border-b" style="border-color:#1E293B;">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center font-display font-bold text-sm" style="background:#F5A623; color:#0F172A; letter-spacing:0.05em;">SH</div>
                <div>
                    <div class="text-xs font-bold tracking-widest uppercase" style="color:#F5A623; font-family:'Space Grotesk',sans-serif; letter-spacing:0.15em;">SELLER'S HUB</div>
                    <div class="text-xs font-medium" style="color:#64748B;">Customer's Analytics</div>
                </div>
            </div>

            @php $activeShop = auth()->user()->shops()->where('is_active', true)->first(); @endphp
            @if($activeShop)
            <div class="mt-3 px-2.5 py-1.5 rounded-md flex items-center gap-2" style="background:rgba(245,166,35,0.1); border:1px solid rgba(245,166,35,0.2);">
                <div class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:#F5A623;"></div>
                <span class="text-xs font-medium truncate" style="color:#F5A623;">{{ $activeShop->shop_name ?? 'Connected' }}</span>
            </div>
            @endif
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
            <p class="px-3 mb-2 text-xs font-semibold uppercase tracking-widest" style="color:#475569;">Main</p>

            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150"
               style="{{ request()->routeIs('dashboard') ? 'background:#F5A623; color:#0F172A;' : 'color:#94A3B8;' }}"
               onmouseover="{{ request()->routeIs('dashboard') ? '' : "this.style.background='rgba(245,166,35,0.1)';this.style.color='#F5A623';" }}"
               onmouseout="{{ request()->routeIs('dashboard') ? '' : "this.style.background='';this.style.color='#94A3B8';" }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                Dashboard
            </a>

            <p class="px-3 mt-4 mb-2 text-xs font-semibold uppercase tracking-widest" style="color:#475569;">Analytics</p>

            <a href="{{ route('analytics.customers') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150"
               style="{{ request()->routeIs('analytics.customers') ? 'background:#F5A623; color:#0F172A;' : 'color:#94A3B8;' }}"
               onmouseover="{{ request()->routeIs('analytics.customers') ? '' : "this.style.background='rgba(245,166,35,0.1)';this.style.color='#F5A623';" }}"
               onmouseout="{{ request()->routeIs('analytics.customers') ? '' : "this.style.background='';this.style.color='#94A3B8';" }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Customer Analytics
            </a>

            <a href="{{ route('analytics.product-audience') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150"
               style="{{ request()->routeIs('analytics.product-audience') ? 'background:#F5A623; color:#0F172A;' : 'color:#94A3B8;' }}"
               onmouseover="{{ request()->routeIs('analytics.product-audience') ? '' : "this.style.background='rgba(245,166,35,0.1)';this.style.color='#F5A623';" }}"
               onmouseout="{{ request()->routeIs('analytics.product-audience') ? '' : "this.style.background='';this.style.color='#94A3B8';" }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Product Audience
            </a>

            <a href="{{ route('analytics.rts') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150"
               style="{{ request()->routeIs('analytics.rts') ? 'background:#F5A623; color:#0F172A;' : 'color:#94A3B8;' }}"
               onmouseover="{{ request()->routeIs('analytics.rts') ? '' : "this.style.background='rgba(245,166,35,0.1)';this.style.color='#F5A623';" }}"
               onmouseout="{{ request()->routeIs('analytics.rts') ? '' : "this.style.background='';this.style.color='#94A3B8';" }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                RTS & Returns
            </a>

            <a href="{{ route('analytics.map') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150"
               style="{{ request()->routeIs('analytics.map*') ? 'background:#F5A623; color:#0F172A;' : 'color:#94A3B8;' }}"
               onmouseover="{{ request()->routeIs('analytics.map*') ? '' : "this.style.background='rgba(245,166,35,0.1)';this.style.color='#F5A623';" }}"
               onmouseout="{{ request()->routeIs('analytics.map*') ? '' : "this.style.background='';this.style.color='#94A3B8';" }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                PH Map
            </a>

            <p class="px-3 mt-4 mb-2 text-xs font-semibold uppercase tracking-widest" style="color:#475569;">System</p>

            <a href="{{ route('settings.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150"
               style="{{ request()->routeIs('settings.*') ? 'background:#F5A623; color:#0F172A;' : 'color:#94A3B8;' }}"
               onmouseover="{{ request()->routeIs('settings.*') ? '' : "this.style.background='rgba(245,166,35,0.1)';this.style.color='#F5A623';" }}"
               onmouseout="{{ request()->routeIs('settings.*') ? '' : "this.style.background='';this.style.color='#94A3B8';" }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
        </nav>

        <!-- User -->
        <div class="px-3 py-4 border-t" style="border-color:#1E293B;">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm font-display" style="background:#F5A623; color:#0F172A;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div>
                    <div class="text-xs font-semibold" style="color:#E2E8F0;">{{ auth()->user()->name }}</div>
                    <div class="text-xs truncate max-w-[130px]" style="color:#475569;">{{ auth()->user()->email }}</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- Top bar -->
        <header class="h-14 bg-white border-b border-gray-100 flex items-center justify-between px-6 flex-shrink-0 shadow-sm z-10 overflow-visible">
            <div>
                <h1 class="text-base font-semibold text-gray-900 font-display">@yield('title', 'Dashboard')</h1>
                <p class="text-xs text-gray-400">@yield('subtitle', '')</p>
            </div>
            <div class="flex items-center gap-3">
                @if(isset($activeShop) && $activeShop || auth()->user()->shops()->where('is_active', true)->exists())
                <form method="POST" action="{{ route('settings.sync') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors cursor-pointer"
                            style="background:#0F172A; color:#F5A623; border-color:#0F172A;"
                            onmouseover="this.style.background='#1E293B';"
                            onmouseout="this.style.background='#0F172A';">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Sync Now
                    </button>
                </form>
                @endif
                @yield('header_actions')
            </div>
        </header>

        @if(session('success'))
        <div class="mx-6 mt-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800 flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
        @endif
        @if(session('info'))
        <div class="mx-6 mt-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2" style="background:#FDF3E0; border:1px solid #F5A623; color:#92400E;">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" style="color:#F5A623;"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            {{ session('info') }}
        </div>
        @endif

        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>
    </div>
</div>

<script>
function handlePreset(sel) {
    const form = sel.closest('.filter-form');
    const fromInput   = form.querySelector('.filter-date-from');
    const toInput     = form.querySelector('.filter-date-to');
    const customRange = form.querySelector('.filter-custom');
    const today = new Date().toISOString().slice(0,10);
    const ago = n => { const d = new Date(); d.setDate(d.getDate()-n); return d.toISOString().slice(0,10); };

    if (sel.value === 'custom') {
        customRange.classList.remove('hidden');
        return;
    }
    customRange.classList.add('hidden');
    switch (sel.value) {
        case 'all': fromInput.value = ''; toInput.value = ''; break;
        case '7d':  fromInput.value = ago(7);  toInput.value = today; break;
        case '30d': fromInput.value = ago(30); toInput.value = today; break;
        case '90d': fromInput.value = ago(90); toInput.value = today; break;
    }
    form.submit();
}
</script>
@stack('scripts')
</body>
</html>
