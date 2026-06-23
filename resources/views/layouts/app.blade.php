<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SH Customer's Analytics — @yield('title', 'Dashboard')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Fira Sans', sans-serif; background: #F1F5F9; }
        .font-display { font-family: 'Fira Sans', sans-serif; font-weight: 600; }
        .font-mono, [class*="font-mono"] { font-family: 'Fira Code', monospace; }
        :root {
            --gold: #F5A623;
            --gold-dark: #D4891A;
            --gold-light: #FDF3E0;
            --dark: #0F172A;
            --dark-2: #1E293B;
            --dark-3: #334155;
        }

        /* Skeleton shimmer */
        @keyframes shimmer {
            0%   { background-position: -400px 0; }
            100% { background-position: 400px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
            background-size: 800px 100%;
            animation: shimmer 1.4s ease-in-out infinite;
            border-radius: 8px;
        }
        .chart-wrap { position: relative; }
        .chart-skeleton {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; gap: 8px; padding: 8px;
            pointer-events: none;
        }
        .chart-skeleton .sk-bar {
            background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
            background-size: 800px 100%;
            animation: shimmer 1.4s ease-in-out infinite;
            border-radius: 4px;
        }

        /* KPI card hover lift */
        .kpi-card {
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            cursor: default;
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px -6px rgba(0,0,0,0.10), 0 4px 8px -2px rgba(0,0,0,0.06);
        }

        /* Empty state */
        .empty-state {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; padding: 40px 20px; text-align: center; gap: 10px;
        }
        .empty-state-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            background: #F1F5F9; margin-bottom: 4px;
        }
        .empty-state p.title { font-size: 13px; font-weight: 600; color: #475569; }
        .empty-state p.hint  { font-size: 12px; color: #94A3B8; max-width: 220px; line-height: 1.5; }

        /* Chart canvas fade-in */
        .chart-canvas { opacity: 0; transition: opacity 0.3s ease; }
        .chart-canvas.loaded { opacity: 1; }

        /* Table row hover */
        tbody tr { transition: background 0.12s ease; }

        /* ── Filter loading states ────────────────────────────────────── */
        /* Top progress bar */
        #filter-progress-bar {
            position: fixed;
            top: 56px; /* below the h-14 header */
            left: 240px; /* right of the w-60 sidebar */
            right: 0;
            height: 3px;
            z-index: 9999;
            display: none;
            background: linear-gradient(90deg, #1E40AF 0%, #3B82F6 35%, #F5A623 65%, #1E40AF 100%);
            background-size: 300% 100%;
            animation: filterProgress 1.4s linear infinite;
        }
        @keyframes filterProgress {
            0%   { background-position: 100% 0; }
            100% { background-position: -200% 0; }
        }

        /* Chart area loading overlay */
        .chart-filter-overlay {
            position: absolute; inset: 0; z-index: 20;
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px;
            animation: cfOverlayIn 0.18s ease;
        }
        @keyframes cfOverlayIn { from { opacity: 0; } to { opacity: 1; } }
        .chart-filter-spinner {
            width: 30px; height: 30px;
            border: 3px solid #E2E8F0;
            border-top-color: #1E40AF;
            border-radius: 50%;
            animation: cfSpin 0.75s linear infinite;
        }
        @keyframes cfSpin { to { transform: rotate(360deg); } }

        /* KPI card pulse while loading */
        @keyframes kpiLoadPulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.42; }
        }
        .kpi-card.is-loading {
            animation: kpiLoadPulse 1.1s ease-in-out infinite !important;
            pointer-events: none;
        }
        .kpi-card.is-loading:hover { transform: none !important; box-shadow: none !important; }

        /* Table body dim while loading */
        tbody.tbody-loading {
            opacity: 0.3;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }
    </style>
    @stack('head')
</head>
<body class="h-full">
<div id="filter-progress-bar"></div>
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
        <header class="h-14 bg-white border-b border-gray-100 flex items-center justify-between px-6 flex-shrink-0 shadow-sm z-[1001] overflow-visible">
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
        // Pre-fill visible date pickers from the hidden inputs; if those are also
        // empty (e.g. user was on "All Time"), default to the last 30 days.
        const [visFrom, visTo] = customRange.querySelectorAll('input[type=date]');
        if (visFrom && !visFrom.value) {
            visFrom.value    = fromInput.value || ago(30);
            fromInput.value  = visFrom.value;
        }
        if (visTo && !visTo.value) {
            visTo.value    = toInput.value || today;
            toInput.value  = visTo.value;
        }
        return;
    }
    customRange.classList.add('hidden');
    switch (sel.value) {
        case 'all': fromInput.value = ''; toInput.value = ''; break;
        case '7d':  fromInput.value = ago(7);  toInput.value = today; break;
        case '30d': fromInput.value = ago(30); toInput.value = today; break;
        case '90d': fromInput.value = ago(90); toInput.value = today; break;
    }
    form.requestSubmit();
}

// ── Filter state persistence (localStorage per page) ─────────────────────────
// On every filter submit: save params keyed by route name.
// On page load with no URL params: restore the last-used filter for this page.
(function () {
    var storageKey = 'filterState_{{ \Route::currentRouteName() }}';

    // Called by the AJAX interceptor after every filter submission
    window.__saveFilterState = function (params) {
        try {
            var obj = {};
            params.forEach(function (v, k) { obj[k] = v; });
            localStorage.setItem(storageKey, JSON.stringify(obj));
        } catch (e) {}
    };

    if (window.location.search.length > 1) {
        // Page loaded with params already in URL (full reload or direct link) — save them
        window.__saveFilterState(new URLSearchParams(window.location.search));
    } else {
        // No params — restore last-used filter unless the user navigated here from the
        // same page (e.g. clicked the nav link to reset filters).
        var referrerPath = '';
        try { referrerPath = new URL(document.referrer).pathname; } catch (e) {}
        if (referrerPath !== window.location.pathname) {
            try {
                var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (saved !== null) {
                    var qs = new URLSearchParams(saved).toString();
                    if (qs) window.location.replace(window.location.pathname + '?' + qs);
                }
            } catch (e) {}
        }
    }
})();

// ── Filter loading helpers ────────────────────────────────────────────────────
function showFilterLoading() {
    var bar = document.getElementById('filter-progress-bar');
    if (bar) bar.style.display = 'block';

    // Overlay spinners on every visible chart
    document.querySelectorAll('.chart-wrap').forEach(function (wrap) {
        if (wrap.querySelector('.chart-filter-overlay')) return;
        if (!wrap.querySelector('.chart-canvas.loaded')) return;
        var o = document.createElement('div');
        o.className = 'chart-filter-overlay';
        o.innerHTML = '<div class="chart-filter-spinner"></div>';
        wrap.appendChild(o);
    });

    // Pulse KPI cards
    document.querySelectorAll('.kpi-card').forEach(function (el) {
        el.classList.add('is-loading');
    });

    // Dim table bodies that have IDs (data tables, not layout tables)
    document.querySelectorAll('tbody[id]').forEach(function (el) {
        el.classList.add('tbody-loading');
    });
}

function hideFilterLoading() {
    var bar = document.getElementById('filter-progress-bar');
    if (bar) bar.style.display = 'none';

    document.querySelectorAll('.chart-filter-overlay').forEach(function (el) {
        el.remove();
    });
    document.querySelectorAll('.kpi-card.is-loading').forEach(function (el) {
        el.classList.remove('is-loading');
    });
    document.querySelectorAll('tbody.tbody-loading').forEach(function (el) {
        el.classList.remove('tbody-loading');
    });
}

// ── AJAX filter interceptor ───────────────────────────────────────────────────
// Catches every .filter-form submit. If the page defines window.__ajaxFilterUpdate,
// runs it instead of reloading. Saves/restores <main> scroll position.
// Uses a generation counter to discard stale responses (abort stale requests).
(function () {
    var mainEl = document.querySelector('main');
    var gen    = 0; // incremented on every submit; stale responses check against this

    document.addEventListener('submit', function (e) {
        if (!e.target.classList.contains('filter-form')) return;
        if (typeof window.__ajaxFilterUpdate !== 'function') return;

        e.preventDefault();

        var form        = e.target;
        var btn         = form.querySelector('[type=submit]');
        var savedScroll = mainEl ? mainEl.scrollTop : 0;
        var thisGen     = ++gen; // capture generation for this specific request

        if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }
        showFilterLoading();

        var params = new URLSearchParams(new FormData(form));
        var url    = form.action + '?' + params;

        history.pushState(null, '', url);
        window.__saveFilterState(params);

        Promise.resolve(window.__ajaxFilterUpdate(form, params, url))
            .then(function () {
                if (thisGen !== gen) return; // newer request already in flight — discard
                hideFilterLoading();
                if (mainEl) mainEl.scrollTop = savedScroll;
                if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
            })
            .catch(function (err) {
                if (thisGen !== gen) return;
                hideFilterLoading();
                console.error('[filter] AJAX failed, falling back to full reload:', err);
                if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
                window.location.href = url;
            });
    });

    window.addEventListener('popstate', function () {
        if (typeof window.__ajaxFilterUpdate !== 'function') return;
        var form = document.querySelector('.filter-form');
        if (!form) return;

        var params      = new URLSearchParams(window.location.search);
        var savedScroll = mainEl ? mainEl.scrollTop : 0;
        var thisGen     = ++gen;

        params.forEach(function (value, key) {
            var el = form.elements[key];
            if (el) el.value = value;
        });
        window.__saveFilterState(params);
        showFilterLoading();

        var url = window.location.pathname + window.location.search;
        Promise.resolve(window.__ajaxFilterUpdate(form, params, url))
            .then(function () {
                if (thisGen !== gen) return;
                hideFilterLoading();
                if (mainEl) mainEl.scrollTop = savedScroll;
            })
            .catch(function () {
                if (thisGen !== gen) return;
                hideFilterLoading();
            });
    });
})();

// ── Live accuracy: pulse polling + 5-min auto-reconcile ───────────────────────
//
//   Page loads      → server already rendered data; reconcile runs at page-load
//   User is active  → 30 s pulse detects webhook-triggered DB changes instantly
//   Every 5 mins    → silent re-fetch catches anything the pulse might miss
//
(function () {
    // Silent refresh: re-runs the current page's filter without showing the full
    // loading UI — just a brief progress-bar flash so the user knows it updated.
    function silentRefresh() {
        if (typeof window.__ajaxFilterUpdate !== 'function') return;
        var form = document.querySelector('.filter-form');
        if (!form) return;
        var params = new URLSearchParams(new FormData(form));
        var url    = form.action + '?' + params;
        var bar    = document.getElementById('filter-progress-bar');
        if (bar) bar.style.display = 'block';
        Promise.resolve(window.__ajaxFilterUpdate(form, params, url))
            .then(function ()  { if (bar) bar.style.display = 'none'; })
            .catch(function () { if (bar) bar.style.display = 'none'; });
    }

    // ── 30-second pulse: detect webhook / reconciliation job changes ──────────
    var lastBust = null;
    setInterval(function () {
        if (document.visibilityState !== 'visible') return;
        fetch('/analytics/pulse', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                if (lastBust === null) { lastBust = d.updated_at; return; }
                if (d.updated_at > lastBust) { lastBust = d.updated_at; silentRefresh(); }
            })
            .catch(function () {});
    }, 30000);

    // ── 5-minute auto-reconcile while page is visible ─────────────────────────
    var reconcileTimer = null;
    function resetTimer() {
        clearInterval(reconcileTimer);
        if (document.visibilityState === 'visible') {
            reconcileTimer = setInterval(function () {
                if (document.visibilityState === 'visible') silentRefresh();
            }, 5 * 60 * 1000);
        }
    }
    document.addEventListener('visibilitychange', resetTimer);
    resetTimer();
})();
</script>
@stack('scripts')
</body>
</html>
