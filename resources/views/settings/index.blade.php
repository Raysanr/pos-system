@extends('layouts.app')
@section('title', 'Settings')
@section('subtitle', 'Connect your Pancake POS account')

@section('content')
<div class="max-w-2xl space-y-6">

    {{-- Step 1: Paste API Key --}}
    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden" id="step1-card">
        <div class="px-6 py-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full bg-blue-700 text-white text-xs font-bold flex items-center justify-center">1</div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Paste your Pancake POS API Key</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Settings → App Settings → API Key → Create</p>
                </div>
            </div>
        </div>
        <div class="px-6 py-5">
            <div class="flex gap-3">
                <input type="text" id="apiKeyInput"
                    placeholder="Paste your API key here..."
                    value="{{ $shop?->api_key ?? '' }}"
                    class="flex-1 rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm font-mono text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="button" id="detectBtn"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg transition-colors cursor-pointer whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Detect Shop
                </button>
            </div>
            <div id="detectStatus" class="mt-3 text-xs hidden"></div>
        </div>
    </div>

    {{-- Step 2: Shop selection (shown after detect) --}}
    <div id="step2-card" class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden {{ $shop ? '' : 'hidden' }}">
        <div class="px-6 py-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full bg-blue-700 text-white text-xs font-bold flex items-center justify-center">2</div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Confirm your shop</h3>
                    <p class="text-xs text-slate-500 mt-0.5">All information is auto-detected from your API key</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.store') }}" id="connectForm">
            @csrf
            <input type="hidden" name="api_key" id="formApiKey" value="{{ $shop?->api_key ?? '' }}">
            <input type="hidden" name="shop_id" id="formShopId" value="{{ $shop?->shop_id ?? '' }}">
            <input type="hidden" name="shop_name" id="formShopName" value="{{ $shop?->shop_name ?? '' }}">

            @if($errors->any())
            <div class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
            @endif

            {{-- Detected shop info display --}}
            <div id="shopInfoDisplay" class="px-6 py-5">
                @if($shop)
                <div class="flex items-start gap-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0 text-blue-700 font-bold text-lg">
                        {{ strtoupper(substr($shop->shop_name ?? 'S', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-bold text-slate-900">{{ $shop->shop_name }}</p>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                Connected
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5 font-mono">Shop ID: {{ $shop->shop_id }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">Last synced: {{ $shop->last_synced_at?->diffForHumans() ?? 'Never' }}</p>
                    </div>
                </div>
                @else
                <div class="text-center py-6 text-slate-400 text-sm" id="noShopMsg">
                    Paste your API key above and click "Detect Shop"
                </div>
                @endif
            </div>

            <div class="px-6 pb-5 flex items-center gap-3 border-t border-slate-100 pt-4">
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Auto-sync interval</label>
                    <select name="sync_interval_hours" class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="1"  {{ $shop?->sync_interval_hours == 1  ? 'selected' : '' }}>Every hour</option>
                        <option value="3"  {{ $shop?->sync_interval_hours == 3  ? 'selected' : '' }}>Every 3 hours</option>
                        <option value="6"  {{ ($shop?->sync_interval_hours ?? 6) == 6 ? 'selected' : '' }}>Every 6 hours</option>
                        <option value="12" {{ $shop?->sync_interval_hours == 12 ? 'selected' : '' }}>Every 12 hours</option>
                        <option value="24" {{ $shop?->sync_interval_hours == 24 ? 'selected' : '' }}>Once a day</option>
                    </select>
                </div>
                <button type="submit" id="connectBtn"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg transition-colors cursor-pointer mt-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ $shop ? 'Save & Re-sync' : 'Connect & Sync' }}
                </button>
            </div>
        </form>
    </div>

    {{-- Sync Log --}}
    @if($shop)
    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden" id="syncLogCard">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-semibold text-slate-800">Sync History</h3>
                <div id="syncIndicator" class="hidden items-center gap-1.5 text-xs text-blue-600 font-medium">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Syncing... (auto-refreshing)
                </div>
            </div>
            <form method="POST" action="{{ route('settings.sync') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium rounded-lg border border-slate-200 transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Sync Now
                </button>
            </form>
        </div>
        <table class="w-full">
            <thead><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Fetched</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Upserted</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50">Started</th>
            </tr></thead>
            <tbody>
                @foreach($syncLogs as $log)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ $log->type }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $log->status === 'success' ? 'bg-green-100 text-green-700' :
                               ($log->status === 'running' ? 'bg-blue-100 text-blue-700 animate-pulse' : 'bg-red-100 text-red-700') }}">
                            {{ ucfirst($log->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">{{ number_format($log->records_fetched) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">{{ number_format($log->records_upserted) }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $log->started_at?->format('M d, H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($syncLogs?->isEmpty())
        <div class="px-6 py-8 text-center text-sm text-slate-400">No sync history yet. Click "Sync Now" to start.</div>
        @endif
    </div>
    @endif

    {{-- How to get API Key guide --}}
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
        <h4 class="text-xs font-semibold text-blue-800 uppercase tracking-wider mb-3">How to get your Pancake POS API Key</h4>
        <ol class="space-y-2 text-xs text-blue-700">
            <li class="flex gap-2.5">
                <span class="w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-bold flex-shrink-0 flex items-center justify-center">1</span>
                Log in to <strong>pos.pages.fm</strong> and open your shop
            </li>
            <li class="flex gap-2.5">
                <span class="w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-bold flex-shrink-0 flex items-center justify-center">2</span>
                Go to <strong>Settings → App Settings → API Key</strong>
            </li>
            <li class="flex gap-2.5">
                <span class="w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-bold flex-shrink-0 flex items-center justify-center">3</span>
                Click <strong>Create</strong> to generate your key, then copy it
            </li>
            <li class="flex gap-2.5">
                <span class="w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-bold flex-shrink-0 flex items-center justify-center">4</span>
                Paste it above — your shop info will be <strong>auto-detected</strong>
            </li>
        </ol>
        <p class="mt-3 text-xs text-blue-500">Rate limit: 1,000 requests/min · 10,000 requests/hour</p>
    </div>

</div>
@endsection

@push('scripts')
<script>
const detectBtn  = document.getElementById('detectBtn');
const apiInput   = document.getElementById('apiKeyInput');
const statusEl   = document.getElementById('detectStatus');
const step2Card  = document.getElementById('step2-card');
const shopDisplay = document.getElementById('shopInfoDisplay');
const formApiKey  = document.getElementById('formApiKey');
const formShopId  = document.getElementById('formShopId');
const formShopName = document.getElementById('formShopName');
const connectBtn  = document.getElementById('connectBtn');

detectBtn.addEventListener('click', async () => {
    const key = apiInput.value.trim();
    if (!key) {
        showStatus('error', 'Please paste your API key first.');
        return;
    }

    detectBtn.disabled = true;
    detectBtn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Detecting...`;
    showStatus('loading', 'Connecting to Pancake POS...');

    try {
        const res = await fetch('{{ route('settings.detect') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ api_key: key }),
        });

        const data = await res.json();

        if (data.success && data.shops?.length) {
            renderShops(data.shops, key);
            showStatus('success', `Found ${data.shops.length} shop(s) — select one below.`);
            step2Card.classList.remove('hidden');
        } else {
            showStatus('error', data.message ?? 'No shops found for this API key.');
        }
    } catch (e) {
        showStatus('error', 'Network error. Please check your connection.');
    } finally {
        detectBtn.disabled = false;
        detectBtn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> Detect Shop`;
    }
});

function renderShops(shops, apiKey) {
    formApiKey.value = apiKey;

    if (shops.length === 1) {
        selectShop(shops[0], apiKey);
        shopDisplay.innerHTML = buildShopCard(shops[0], true);
        return;
    }

    // Multiple shops — render selection cards
    shopDisplay.innerHTML = `
        <p class="text-xs font-semibold text-slate-600 mb-3">Select a shop to connect:</p>
        <div class="space-y-2">
            ${shops.map((s, i) => `
            <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors
                ${i === 0 ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-blue-300'}">
                <input type="radio" name="shop_select" value="${i}" ${i === 0 ? 'checked' : ''}
                    class="text-blue-600 focus:ring-blue-500" onchange="selectShop(${JSON.stringify(s).replace(/"/g,'&quot;')}, '${apiKey}')">
                <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm flex-shrink-0">
                    ${s.name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900">${s.name}</p>
                    <p class="text-xs text-slate-400 font-mono">ID: ${s.id}</p>
                </div>
            </label>`).join('')}
        </div>`;

    // Auto-select first
    selectShop(shops[0], apiKey);
}

function selectShop(shop, apiKey) {
    formApiKey.value  = apiKey;
    formShopId.value  = shop.id;
    formShopName.value = shop.name;
    connectBtn.textContent = `Connect "${shop.name}"`;
}

function buildShopCard(shop, selected) {
    return `
    <div class="flex items-start gap-4 p-4 bg-green-50 border border-green-200 rounded-xl">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0 text-blue-700 font-bold text-lg">
            ${shop.name.charAt(0).toUpperCase()}
        </div>
        <div>
            <div class="flex items-center gap-2">
                <p class="text-sm font-bold text-slate-900">${shop.name}</p>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                    Detected
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5 font-mono">Shop ID: ${shop.id}</p>
        </div>
    </div>`;
}

function showStatus(type, msg) {
    statusEl.classList.remove('hidden');
    const styles = {
        loading: 'text-blue-600',
        success: 'text-green-600',
        error:   'text-red-600',
    };
    statusEl.className = `mt-3 text-xs ${styles[type] ?? 'text-slate-600'}`;
    statusEl.textContent = msg;
}

// Auto-refresh sync log while a job is running
const hasRunning = {{ $syncLogs?->where('status', 'running')->count() > 0 ? 'true' : 'false' }};
const syncIndicator = document.getElementById('syncIndicator');

if (hasRunning && syncIndicator) {
    syncIndicator.classList.remove('hidden');
    syncIndicator.classList.add('flex');
    let countdown = 5;
    const timer = setInterval(() => {
        countdown--;
        syncIndicator.querySelector('svg + span') && null;
        if (countdown <= 0) {
            clearInterval(timer);
            window.location.reload();
        }
    }, 1000);

    // Show countdown in the indicator
    const countEl = document.createElement('span');
    syncIndicator.appendChild(countEl);
    const countTimer = setInterval(() => {
        countdown > 0
            ? (countEl.textContent = ` (refresh in ${countdown}s)`)
            : clearInterval(countTimer);
    }, 200);
}

// Also start polling after Sync Now form submit
document.querySelectorAll('form[action="{{ route('settings.sync') }}"]').forEach(form => {
    form.addEventListener('submit', () => {
        if (syncIndicator) {
            syncIndicator.classList.remove('hidden');
            syncIndicator.classList.add('flex');
        }
        setTimeout(() => window.location.reload(), 6000);
    });
});
</script>
@endpush
