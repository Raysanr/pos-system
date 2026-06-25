@extends('layouts.app')
@section('title', 'PH Map')
@section('subtitle', 'Order heatmap — Province · City')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
@endpush

@section('header_actions')
<div class="flex items-center gap-3 flex-wrap">
    @include('partials.filter-bar', [
        'filterRoute'   => route('analytics.map'),
        'products'      => $products,
        'productFilter' => $productFilter,
        'datePreset'    => $datePreset,
        'dateFrom'      => $dateFrom,
        'dateTo'        => $dateTo,
        'extraFilters'  => '
            <select name="status" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium text-slate-700 cursor-pointer">
                <option value="all"       ' . ($status === 'all'       ? 'selected' : '') . '>All Orders</option>
                <option value="delivered" ' . ($status === 'delivered' ? 'selected' : '') . '>Delivered</option>
                <option value="rts"       ' . ($status === 'rts'       ? 'selected' : '') . '>RTS</option>
                <option value="returned"  ' . ($status === 'returned'  ? 'selected' : '') . '>Returned</option>
                <option value="cancelled" ' . ($status === 'cancelled' ? 'selected' : '') . '>Cancelled</option>
            </select>
        ',
    ])
    <div class="flex items-center gap-1.5 pl-3 border-l border-slate-200">
        <span class="text-xs text-slate-500 font-medium">Level:</span>
        <button id="level-btn-province" onclick="setLevel('province')" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg bg-blue-700 text-white cursor-pointer">Province</button>
        <button id="level-btn-city" onclick="setLevel('city')" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 cursor-pointer">City</button>
    </div>
</div>
@endsection

@section('content')
<div id="map-breadcrumb" class="hidden items-center gap-1.5 text-xs mb-3 bg-white rounded-xl border border-blue-100 px-4 py-2.5 shadow-sm"></div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden isolate">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Philippine Order Map</h3>
                <p class="text-xs text-slate-400">Circle size = volume · Color = RTS rate · Click circle or row to drill in</p>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-500">
                <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-full" style="background:#22c55e"></div>&lt;10% RTS</div>
                <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-full" style="background:#f59e0b"></div>10–20%</div>
                <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-full" style="background:#ef4444"></div>&gt;20%</div>
            </div>
        </div>
        <div id="ph-map" style="height:540px;"></div>
    </div>

    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800"><span id="table-level-label">Province</span> Rankings</h3>
            <p class="text-xs text-slate-400">Sorted by volume · Click row to pan map</p>
        </div>
        <div class="overflow-y-auto" style="max-height:468px">
            <table class="w-full">
                <thead class="sticky top-0"><tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50">#</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50" id="table-col-label">Province</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Orders</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">RTS%</th>
                </tr></thead>
                <tbody id="rankings-tbody">
                    <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 flex items-center gap-2 flex-wrap">
    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">View Mode:</span>
    <button onclick="setViewMode('volume')" id="btn-volume" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-700 text-white transition-colors cursor-pointer">Volume</button>
    <button onclick="setViewMode('rts')" id="btn-rts" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">RTS Rate</button>
    <button onclick="setViewMode('revenue')" id="btn-revenue" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">Revenue</button>
</div>
@endsection

@push('scripts')
<script id="map-data" type="application/json"><?php echo json_encode($mapData); ?></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
let currentData  = JSON.parse(document.getElementById('map-data').textContent);
let currentMode  = 'volume';
let currentLevel = 'province';
let currentProvinceFilter = null;
let currentCityFilter     = null;
let markers   = [];
let map;
let isLoading = false;

let DATE_FROM       = (document.querySelector('[name=date_from]')      || {}).value || '';
let DATE_TO         = (document.querySelector('[name=date_to]')        || {}).value || '';
let STATUS          = (document.querySelector('[name=status]')         || {}).value || 'all';
let PRODUCT_FILTER  = (document.querySelector('[name=product_filter]') || {}).value || '';

// ── Helpers ──────────────────────────────────────────────────────────────────
function getCoords(d) {
    // Coordinates come from the server (geocache DB). Fallback: parent province from data.
    if (d.lat != null && d.lng != null) return [d.lat, d.lng];
    return null;
}

function getRtsColor(rate) {
    if (rate > 20) return '#ef4444';
    if (rate > 10) return '#f59e0b';
    return '#22c55e';
}

// Fixed pixel radii — circles stay the same pixel size when zoomed in so the map stays visible
function levelMaxPx() {
    if (currentLevel === 'city') return 34;
    return 48; // province
}

function getRadius(d) {
    const max        = levelMaxPx();
    const minR       = 8;
    const maxOrders  = Math.max(...currentData.map(x => x.total_orders),  1);
    const maxRevenue = Math.max(...currentData.map(x => x.total_revenue), 1);
    if (currentMode === 'volume')  return Math.max(minR, Math.sqrt(d.total_orders  / maxOrders)  * max);
    if (currentMode === 'revenue') return Math.max(minR, Math.sqrt(d.total_revenue / maxRevenue) * max);
    const rateForSize = STATUS === 'returned' ? d.returned_rate : d.rts_rate;
    return Math.max(minR, (rateForSize / 100) * max + minR);
}

function fmt(v) {
    if (v >= 1e6) return '₱'+(v/1e6).toFixed(1)+'M';
    if (v >= 1e3) return '₱'+(v/1e3).toFixed(0)+'K';
    return '₱'+v.toLocaleString();
}

function titleCase(s) { return (s||'').replace(/-/g,' ').replace(/\b\w/g,c=>c.toUpperCase()); }

// ── Render ───────────────────────────────────────────────────────────────────
function renderMarkers() {
    markers.forEach(m => map.removeLayer(m));
    markers = [];

    currentData.forEach(d => {
        const coords = getCoords(d);
        if (!coords) return;

        const bubbleRate = STATUS === 'returned' ? d.returned_rate : d.rts_rate;
        const color = getRtsColor(bubbleRate);
        const circle = L.circleMarker(coords, {
            radius: getRadius(d),
            fillColor: color, color:'rgba(255,255,255,0.85)',
            weight:1.5, opacity:1, fillOpacity:0.82,
        });

        const drillFn = currentLevel === 'province'
            ? `drillDown('${d.name.replace(/'/g,"\\'")}',null)`
            : null;
        const drillBtn = drillFn
            ? `<div style="margin-top:8px;text-align:center">
                 <button onclick="${drillFn}"
                   style="font-size:10px;padding:3px 10px;background:#1e40af;color:#fff;border:none;border-radius:4px;cursor:pointer">
                   View Cities ›
                 </button></div>` : '';

        const parentLabel = [d.city, d.province].filter(Boolean).map(titleCase).join(', ');
        const rtLabel = STATUS === 'returned' ? 'Returned' : 'RTS';
        const rtCount = STATUS === 'returned' ? d.returned_count : d.rts_count;
        const rtRate  = STATUS === 'returned' ? d.returned_rate  : d.rts_rate;
        circle.bindPopup(`
          <div style="min-width:165px;font-family:-apple-system,sans-serif">
            <div style="font-weight:700;font-size:13px;color:#1e293b;margin-bottom:2px;border-bottom:1px solid #f1f5f9;padding-bottom:6px">${titleCase(d.name)}${parentLabel ? `<div style="font-weight:400;font-size:10px;color:#64748b;margin-top:2px">${parentLabel}</div>` : ''}</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;font-size:11px;color:#475569">
              <span>Orders</span><strong style="color:#1e40af;text-align:right">${d.total_orders.toLocaleString()}</strong>
              <span>Revenue</span><strong style="color:#059669;text-align:right">${fmt(d.total_revenue)}</strong>
              <span>${rtLabel}</span><strong style="color:#dc2626;text-align:right">${rtCount} (${rtRate}%)</strong>
              <span>Avg Order</span><strong style="color:#7c3aed;text-align:right">${fmt(d.avg_order_value)}</strong>
            </div>${drillBtn}
          </div>`, { maxWidth:230 });

        circle.addTo(map);
        markers.push(circle);
    });
}

function updateTable() {
    const tbody = document.getElementById('rankings-tbody');
    if (!tbody) return;

    if (!currentData.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">No data for this selection.</td></tr>';
        return;
    }

    tbody.innerHTML = currentData.map((d,i) => {
        const rate = STATUS === 'returned' ? d.returned_rate : d.rts_rate;
        const cls = rate > 20 ? 'bg-red-100 text-red-700' : rate > 10 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700';
        return `<tr class="border-t border-slate-100 hover:bg-blue-50/40 cursor-pointer location-row" data-index="${i}">
            <td class="px-3 py-2.5 text-xs text-slate-400">${i+1}</td>
            <td class="px-3 py-2.5">
                <div class="text-xs font-medium text-slate-700">${titleCase(d.name)}</div>
                ${(d.city || d.province) ? `<div class="text-xs text-slate-400 mt-0.5">${[d.city, d.province].filter(Boolean).map(titleCase).join(', ')}</div>` : ''}
            </td>
            <td class="px-3 py-2.5 text-xs text-slate-700 text-right font-mono">${d.total_orders.toLocaleString()}</td>
            <td class="px-3 py-2.5 text-right"><span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium ${cls}">${rate}%</span></td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('.location-row').forEach(row => {
        row.addEventListener('click', () => {
            const idx = parseInt(row.dataset.index, 10);
            const item = currentData[idx];
            const coords = item ? getCoords(item) : null;
            if (coords) {
                const maxZoom = { province:8, city:10 }[currentLevel] ?? 10;
                map.setView(coords, Math.min(map.getZoom(), maxZoom), { animate:true });
                if (markers[idx]) markers[idx].openPopup();
            }
        });
    });
}

function updateBreadcrumb() {
    const bc = document.getElementById('map-breadcrumb');
    if (!bc) return;
    if (currentLevel === 'province') { bc.classList.replace('flex','hidden'); return; }

    bc.classList.replace('hidden','flex');
    let parts = [`<button onclick="setLevel('province')" class="text-blue-600 hover:underline font-medium">🗺 All Provinces</button>`];
    if (currentProvinceFilter) {
        const pd = titleCase(currentProvinceFilter);
        parts.push(`<span class="text-slate-400">›</span><span class="text-slate-700 font-medium">${pd}</span>`);
    }
    bc.innerHTML = parts.join(' ');
}

function updateLevelUI() {
    const levelLabels = { province:'Province', city:'City / Municipality' };
    document.getElementById('table-level-label').textContent = levelLabels[currentLevel] ?? currentLevel;
    document.getElementById('table-col-label').textContent   = levelLabels[currentLevel] ?? currentLevel;

    ['province','city'].forEach(l => {
        const btn = document.getElementById('level-btn-'+l);
        if (!btn) return;
        btn.className = l === currentLevel
            ? 'inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg bg-blue-700 text-white cursor-pointer'
            : 'inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 cursor-pointer';
    });
}

// ── Data loading ──────────────────────────────────────────────────────────────
async function drillDown(name, cityName, levelOverride) {
    if (isLoading) return;
    let level = levelOverride;
    if (!level) {
        if (name) { level = 'city'; }
        else      { level = 'province'; }
    }
    currentLevel          = level;
    currentProvinceFilter = level !== 'province' ? (name || currentProvinceFilter) : null;
    currentCityFilter     = null;
    await loadData();
}

async function setLevel(level) {
    await drillDown(null, null, level);
}

async function loadData() {
    if (isLoading) return;
    isLoading = true;
    document.getElementById('rankings-tbody').innerHTML =
        '<tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">Loading…</td></tr>';

    const params = new URLSearchParams({ level:currentLevel, date_from:DATE_FROM, date_to:DATE_TO, status:STATUS, product_filter:PRODUCT_FILTER });
    if (currentProvinceFilter) params.set('province_filter', currentProvinceFilter);
    if (currentCityFilter)     params.set('city_filter',     currentCityFilter);

    try {
        const res = await fetch('/analytics/map/data?'+params, {
            headers:{ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
        });
        currentData = await res.json();
    } catch(e) { currentData = []; }

    renderMarkers();
    updateTable();
    updateBreadcrumb();
    updateLevelUI();
    fitToData();
    isLoading = false;
}

function fitToData() {
    const pts = currentData.filter(d => d.lat != null && d.lng != null).map(d => [d.lat, d.lng]);
    if (!pts.length) {
        map.fitBounds([[4.5,116.5],[21.0,127.0]], { animate: true });
        return;
    }
    if (pts.length === 1) {
        const zoom = currentProvinceFilter ? 12 : 9;
        map.setView(pts[0], zoom, { animate: true });
        return;
    }
    // Cap zoom: stay wide enough to show context; drilled-in province can go closer
    const maxZoom = currentProvinceFilter ? 13 : (currentLevel === 'province' ? 8 : 9);
    map.fitBounds(pts, { padding: [36, 36], maxZoom, animate: true });
}

function setViewMode(mode) {
    currentMode = mode;
    ['volume','rts','revenue'].forEach(m => {
        const btn = document.getElementById('btn-'+m);
        if (!btn) return;
        btn.className = m === mode
            ? 'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-700 text-white transition-colors cursor-pointer'
            : 'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer';
    });
    renderMarkers();
}

// ── Init ──────────────────────────────────────────────────────────────────────
map = L.map('ph-map', { center:[12.1,122.5], zoom:6, zoomControl:true, scrollWheelZoom:true });
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains:'abcd', maxZoom:19
}).addTo(map);
map.fitBounds([[4.5,116.5],[21.0,127.0]]);


if (currentData.length > 0) {
    renderMarkers();
    updateTable();
    updateLevelUI();
    fitToData();
}

window.__ajaxFilterUpdate = async function (form, params) {
    DATE_FROM      = params.get('date_from')      || '';
    DATE_TO        = params.get('date_to')        || '';
    STATUS         = params.get('status')         || 'all';
    PRODUCT_FILTER = params.get('product_filter') || '';
    currentLevel          = 'province';
    currentProvinceFilter = null;
    currentCityFilter     = null;
    await loadData();
};
</script>
@endpush
