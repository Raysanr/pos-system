@extends('layouts.app')
@section('title', 'Sales Forecast')
@section('subtitle', '90-day history → 30-day projection per product')

@section('content')
@php
    $PALETTE = ['#2563EB','#f59e0b','#10b981','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'];
@endphp

<div x-data="sfApp()" x-init="init()">

{{-- ── KPI strip ──────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Projected Orders (30d)</div>
        <div class="text-2xl font-bold text-slate-800">{{ number_format($totalProjectedOrders) }}</div>
        <div class="text-xs text-slate-400 mt-0.5">across all products</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Projected Revenue (30d)</div>
        <div class="text-2xl font-bold text-slate-800">₱{{ number_format($totalProjectedRevenue) }}</div>
        <div class="text-xs text-slate-400 mt-0.5">delivered revenue estimate</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Top Product (30d forecast)</div>
        @if($topProduct)
        <div class="text-base font-bold text-slate-800 leading-snug truncate">{{ ucwords($topProduct['product']) }}</div>
        <div class="text-sm font-bold mt-0.5" style="color:#2563eb;">₱{{ number_format($topProduct['projected_revenue']) }}</div>
        @else
        <div class="text-2xl font-bold text-slate-300">—</div>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Products Tracked</div>
        <div class="text-2xl font-bold text-slate-800">{{ count($forecasts) }}</div>
        <div class="text-xs text-slate-400 mt-0.5">with 90-day history</div>
    </div>
</div>

@if(count($forecasts) === 0)
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-16 text-center">
        <p class="text-sm font-medium text-slate-400">Not enough order history to generate a forecast.</p>
        <p class="text-xs text-slate-300 mt-1">Forecasts require at least some delivered orders in the last 90 days.</p>
    </div>
@else

{{-- ── Product cards ───────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 mb-5">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800">Products</h3>
            <p class="text-xs text-slate-400 mt-0.5">Select up to 4 products to compare on the chart</p>
        </div>
        <button x-show="selected.length > 0" x-cloak
                @click="clearAll()"
                class="text-xs font-medium px-3 py-1.5 rounded-lg cursor-pointer flex-shrink-0"
                style="color:#64748B;background:#F1F5F9;"
                onmouseover="this.style.background='#E2E8F0'" onmouseout="this.style.background='#F1F5F9'">
            Clear all
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        @foreach($forecasts as $i => $p)
        @php
            $color = $PALETTE[$i % count($PALETTE)];
            $trendIcon = $p['trend'] === 'up' ? '↑' : ($p['trend'] === 'down' ? '↓' : '→');
            $trendColor = $p['trend'] === 'up' ? '#10b981' : ($p['trend'] === 'down' ? '#ef4444' : '#94a3b8');
        @endphp
        <div @click="toggle({{ $i }})"
             class="rounded-xl border p-4 cursor-pointer transition-all"
             :style="isSelected({{ $i }})
                 ? 'border-color:{{ $color }};box-shadow:0 0 0 1.5px {{ $color }};background:#fff;'
                 : 'border-color:#E2E8F0;background:#F8FAFC;'">

            <div class="flex items-start justify-between mb-2">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:{{ $color }};"></div>
                    <span class="text-xs font-semibold text-slate-700 truncate">{{ ucwords($p['product']) }}</span>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ml-2" style="color:{{ $trendColor }};">{{ $trendIcon }}</span>
            </div>

            <div class="mt-3 space-y-1.5">
                <div class="flex justify-between items-center">
                    <span class="text-xs text-slate-400">Proj. orders / 30d</span>
                    <span class="text-xs font-bold text-slate-700 tabular-nums">{{ number_format($p['projected_orders']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-slate-400">Proj. revenue / 30d</span>
                    <span class="text-xs font-bold tabular-nums" style="color:#2563EB;">₱{{ number_format($p['projected_revenue']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-slate-400">Actual 90d orders</span>
                    <span class="text-xs font-medium text-slate-500 tabular-nums">{{ number_format($p['actual_90_orders']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-slate-400">Daily avg</span>
                    <span class="text-xs font-medium text-slate-500 tabular-nums">{{ $p['daily_avg'] }} orders/day</span>
                </div>
            </div>

            {{-- Mini trend bar --}}
            <div class="mt-3 h-8 relative" style="overflow:hidden;">
                <canvas id="mini-{{ $i }}" style="position:absolute;inset:0;width:100%;height:100%;"></canvas>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Full chart ───────────────────────────────────────────────────────── --}}
<div x-show="selected.length > 0" x-cloak class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 mb-5">
    <div class="flex items-center justify-between mb-1">
        <h3 class="text-sm font-semibold text-slate-800">Order Forecast</h3>
        <div class="flex items-center gap-3">
            <span class="flex items-center gap-1.5 text-xs text-slate-400">
                <span class="inline-block w-6 h-0.5 rounded" style="background:#64748b;"></span> Actual (90d)
            </span>
            <span class="flex items-center gap-1.5 text-xs text-slate-400">
                <span class="inline-block w-6 border-t-2 border-dashed" style="border-color:#64748b;"></span> Projected (30d)
            </span>
        </div>
    </div>
    <p class="text-xs text-slate-400 mb-4">Dashed portion is the 30-day projection using linear trend from last 30 days</p>
    <div style="position:relative;height:300px;">
        <canvas id="mainChart"></canvas>
    </div>
</div>

{{-- ── Revenue forecast chart ───────────────────────────────────────────── --}}
<div x-show="selected.length > 0" x-cloak class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
    <div class="flex items-center justify-between mb-1">
        <h3 class="text-sm font-semibold text-slate-800">Revenue Forecast</h3>
    </div>
    <p class="text-xs text-slate-400 mb-4">Projected delivered revenue based on trend — actual revenue may vary by RTS rate</p>
    <div style="position:relative;height:300px;">
        <canvas id="revenueChart"></canvas>
    </div>
</div>

{{-- Empty state --}}
<div x-show="selected.length === 0" class="text-center py-10 text-slate-300">
    <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
    </svg>
    <p class="text-sm font-medium text-slate-400">Select a product above to see its forecast</p>
</div>

@endif

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const FORECASTS   = @json($forecasts);
const HIST_DATES  = @json($histDates);
const FUT_DATES   = @json($futureDates);
const PALETTE     = @json($PALETTE);

let mainChart    = null;
let revenueChart = null;
const miniCharts = {};

function fmtDate(s) {
    const d = new Date(s + 'T00:00:00');
    return (d.getMonth()+1) + '/' + d.getDate();
}

function buildMiniChart(idx) {
    const canvas = document.getElementById('mini-' + idx);
    if (!canvas || miniCharts[idx]) return;
    const p = FORECASTS[idx];
    const color = PALETTE[idx % PALETTE.length];
    miniCharts[idx] = new Chart(canvas, {
        type: 'line',
        data: {
            labels: HIST_DATES,
            datasets: [{
                data: p.history_orders,
                borderColor: color,
                borderWidth: 1.5,
                pointRadius: 0,
                tension: 0.3,
                fill: true,
                backgroundColor: color + '20',
            }]
        },
        options: {
            responsive: false,
            animation: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false, beginAtZero: true } }
        }
    });
}

function buildMainChart(selectedIdxs) {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;
    if (mainChart) { mainChart.destroy(); }

    const allDates  = [...HIST_DATES, ...FUT_DATES];
    const histLen   = HIST_DATES.length;
    const datasets  = [];

    selectedIdxs.forEach(idx => {
        const p     = FORECASTS[idx];
        const color = PALETTE[idx % PALETTE.length];
        const name  = p.product.replace(/\b\w/g, c => c.toUpperCase());

        // Actual: hist + null padding for future
        const actual = [...p.history_orders, ...Array(FUT_DATES.length).fill(null)];
        // Projected: null padding for hist, then future (with one overlap point at boundary)
        const projected = [...Array(histLen - 1).fill(null), p.history_orders[histLen - 1], ...p.future_orders];

        datasets.push({
            label:       name + ' (actual)',
            data:        actual,
            borderColor: color,
            borderWidth: 2,
            pointRadius: 0,
            tension:     0.3,
            spanGaps:    false,
        });
        datasets.push({
            label:          name + ' (forecast)',
            data:           projected,
            borderColor:    color,
            borderWidth:    2,
            borderDash:     [5, 4],
            pointRadius:    0,
            tension:        0.3,
            spanGaps:       false,
            backgroundColor: color + '10',
            fill:           false,
        });
    });

    mainChart = new Chart(canvas, {
        type: 'line',
        data: { labels: allDates.map(fmtDate), datasets },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            interaction:         { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 24, padding: 12 } },
                tooltip: {
                    callbacks: {
                        label: c => ' ' + c.dataset.label + ': ' + (c.raw !== null ? c.raw.toLocaleString() : '—') + ' orders'
                    }
                }
            },
            scales: {
                x: {
                    ticks: { font: { size: 9 }, maxTicksLimit: 20 },
                    grid:  { color: '#f1f5f9' }
                },
                y: {
                    beginAtZero: true,
                    ticks:       { font: { size: 10 } },
                    grid:        { color: '#f1f5f9' }
                }
            }
        }
    });

    // Shade the forecast region
    const totalLabels = allDates.length;
    mainChart.options.plugins.annotation = {};
}

function buildRevenueChart(selectedIdxs) {
    const canvas = document.getElementById('revenueChart');
    if (!canvas) return;
    if (revenueChart) { revenueChart.destroy(); }

    const allDates = [...HIST_DATES, ...FUT_DATES];
    const histLen  = HIST_DATES.length;
    const datasets = [];

    selectedIdxs.forEach(idx => {
        const p     = FORECASTS[idx];
        const color = PALETTE[idx % PALETTE.length];
        const name  = p.product.replace(/\b\w/g, c => c.toUpperCase());

        const actual    = [...p.history_revenue, ...Array(FUT_DATES.length).fill(null)];
        const projected = [...Array(histLen - 1).fill(null), p.history_revenue[histLen - 1], ...p.future_revenue];

        datasets.push({
            label:       name + ' (actual)',
            data:        actual,
            borderColor: color,
            borderWidth: 2,
            pointRadius: 0,
            tension:     0.3,
        });
        datasets.push({
            label:       name + ' (forecast)',
            data:        projected,
            borderColor: color,
            borderWidth: 2,
            borderDash:  [5, 4],
            pointRadius: 0,
            tension:     0.3,
        });
    });

    revenueChart = new Chart(canvas, {
        type: 'line',
        data: { labels: allDates.map(fmtDate), datasets },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            interaction:         { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 24, padding: 12 } },
                tooltip: {
                    callbacks: {
                        label: c => ' ' + c.dataset.label + ': ₱' + (c.raw !== null ? Math.round(c.raw).toLocaleString() : '—')
                    }
                }
            },
            scales: {
                x: { ticks: { font: { size: 9 }, maxTicksLimit: 20 }, grid: { color: '#f1f5f9' } },
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 }, callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) },
                    grid:  { color: '#f1f5f9' }
                }
            }
        }
    });
}

try { (function sfAppDef() {

window.sfApp = function() {
    return {
        selected: [],

        init() {
            // Build mini sparklines after DOM is ready
            this.$nextTick(() => {
                FORECASTS.forEach((_, idx) => buildMiniChart(idx));
            });
        },

        isSelected(idx) { return this.selected.includes(idx); },

        toggle(idx) {
            if (this.isSelected(idx)) {
                this.selected = this.selected.filter(i => i !== idx);
            } else {
                if (this.selected.length >= 4) return; // max 4
                this.selected = [...this.selected, idx];
            }
            this.$nextTick(() => {
                if (this.selected.length > 0) {
                    buildMainChart(this.selected);
                    buildRevenueChart(this.selected);
                } else {
                    if (mainChart)    { mainChart.destroy();    mainChart    = null; }
                    if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
                }
            });
        },

        clearAll() {
            this.selected = [];
            if (mainChart)    { mainChart.destroy();    mainChart    = null; }
            if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
        }
    };
};

})(); } catch(e) { console.error('sfApp:', e); }
</script>
@endpush
