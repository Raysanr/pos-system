@extends('layouts.app')
@section('title', 'Product Comparison')
@section('subtitle', 'LTV, growth & acquisition quality — select products to compare')

@section('content')
@php
    $fmt = fn($v) => $v >= 1e6 ? '₱'.number_format($v/1e6,1).'M' : ($v >= 1e3 ? '₱'.number_format($v/1e3,0).'K' : '₱'.number_format($v,0));
@endphp

<div x-data="pcApp()" x-init="init()">

{{-- ── KPI strip ──────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Overall Avg LTV</div>
        <div class="text-2xl font-bold text-slate-800">{{ $fmt($overallAvgLtv) }}</div>
        <div class="text-xs text-slate-400 mt-0.5">across {{ number_format($totalCustomers) }} customers</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Best Repeat Rate</div>
        <div class="text-2xl font-bold" style="color:#059669;">{{ $bestRepeat ? $bestRepeat['repeat_rate'].'%' : '—' }}</div>
        <div class="text-xs text-slate-400 mt-0.5 truncate capitalize">{{ $bestRepeat ? $bestRepeat['product'] : '' }}</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">New Customers — {{ $lastMonthLabel }}</div>
        <div class="text-2xl font-bold text-slate-800">{{ number_format($totalLastMonth) }}</div>
        <div class="text-xs text-slate-400 mt-0.5">across all products</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Lowest 1st-Order RTS</div>
        @if($lowestRts)
        <div class="text-2xl font-bold" style="color:#2563EB;">{{ $lowestRts['rts_rate'] }}%</div>
        <div class="text-xs text-slate-400 mt-0.5 truncate capitalize">{{ $lowestRts['product'] }}</div>
        @else
        <div class="text-2xl font-bold text-slate-300">—</div>
        @endif
    </div>
</div>

{{-- ── Product cards ──────────────────────────────────────────────────── --}}
<div class="mb-5">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-800">Products</h3>
            <p class="text-xs text-slate-400 mt-0.5"
               x-text="selected.length === 0 ? 'Click to select and compare' : selected.length + ' selected — see comparison below'"></p>
        </div>
        <button x-show="selected.length > 0" x-cloak
                @click="clearAll()"
                class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors cursor-pointer"
                style="color:#64748B;background:#F1F5F9;"
                onmouseover="this.style.background='#E2E8F0'" onmouseout="this.style.background='#F1F5F9'">
            Clear
        </button>
    </div>

    @if(count($productData) === 0)
        <div class="text-center py-16 text-slate-400 text-sm">No data yet — sync more orders to build product profiles.</div>
    @else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5">
        @foreach($productData as $i => $p)
        @php
            $barPct      = $maxLtv > 0 ? round($p['avg_ltv'] / $maxLtv * 100) : 0;
            $repeatColor = $p['repeat_rate'] >= 50 ? '#059669' : ($p['repeat_rate'] >= 30 ? '#d97706' : '#dc2626');
            $rtsColor    = $p['rts_rate'] === null ? '#94A3B8' : ($p['rts_rate'] <= 15 ? '#059669' : ($p['rts_rate'] <= 30 ? '#d97706' : '#dc2626'));
            $hasGrowth   = $p['growth_rate'] !== null;
            $growthPos   = $hasGrowth && $p['growth_rate'] >= 0;
            $growthColor = !$hasGrowth ? '#94A3B8' : ($growthPos ? '#059669' : '#dc2626');
        @endphp
        <div @click="toggle({{ $i }})"
             class="rounded-xl border p-3.5 cursor-pointer transition-all select-none"
             :style="isSelected({{ $i }})
                 ? 'background:'+PALETTE[{{ $i }}%PALETTE.length]+'12;border-color:'+PALETTE[{{ $i }}%PALETTE.length]+';box-shadow:0 0 0 1.5px '+PALETTE[{{ $i }}%PALETTE.length]+';'
                 : 'background:#fff;border-color:#F1F5F9;'">

            {{-- Top row --}}
            <div class="flex items-center justify-between mb-2.5">
                <div class="w-2 h-2 rounded-full flex-shrink-0"
                     :style="'background:'+PALETTE[{{ $i }}%PALETTE.length]"></div>
                <div x-show="isSelected({{ $i }})"
                     class="w-4 h-4 rounded-full flex items-center justify-center flex-shrink-0"
                     :style="'background:'+PALETTE[{{ $i }}%PALETTE.length]">
                    <svg class="w-2.5 h-2.5" fill="white" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>

            {{-- Product name --}}
            <div class="text-xs font-semibold text-slate-800 capitalize leading-snug mb-2.5"
                 style="min-height:2.4em;" title="{{ $p['product'] }}">{{ $p['product'] }}</div>

            {{-- LTV --}}
            <div class="text-lg font-bold text-slate-900 tabular-nums leading-none">{{ $fmt($p['avg_ltv']) }}</div>
            <div class="text-xs text-slate-400 mb-1.5">avg LTV</div>
            <div class="bg-slate-100 rounded-full h-1 mb-2.5">
                <div class="h-1 rounded-full"
                     :style="'width:{{ $barPct }}%;background:'+PALETTE[{{ $i }}%PALETTE.length]"></div>
            </div>

            {{-- New customers + growth --}}
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-slate-600 font-medium tabular-nums">{{ number_format($p['last_month']) }} new</span>
                @if($hasGrowth)
                <span class="text-xs font-semibold tabular-nums" style="color:{{ $growthColor }};">
                    {{ $growthPos ? '+' : '' }}{{ $p['growth_rate'] }}%
                </span>
                @else
                <span class="text-xs" style="color:#CBD5E1;">—</span>
                @endif
            </div>

            {{-- RTS + repeat --}}
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium" style="color:{{ $rtsColor }};">
                    RTS {{ $p['rts_rate'] !== null ? $p['rts_rate'].'%' : '—' }}
                </span>
                <span class="text-xs font-medium" style="color:{{ $repeatColor }};">
                    {{ $p['repeat_rate'] }}% rep.
                </span>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ── Empty state ─────────────────────────────────────────────────────── --}}
<div x-show="selected.length === 0" class="text-center py-12 text-slate-300">
    <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <p class="text-sm font-medium text-slate-400">Select products above to compare</p>
</div>

{{-- ── Comparison panel ────────────────────────────────────────────────── --}}
<div x-show="selected.length > 0" x-cloak class="space-y-5">

    {{-- Stats table --}}
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Comparison</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left pb-3 text-xs font-semibold text-slate-400 uppercase tracking-wider" style="min-width:140px;">Metric</th>
                        <template x-for="idx in selected" :key="'h'+idx">
                            <th class="text-right pb-3 text-xs font-semibold uppercase tracking-wider px-3"
                                :style="'color:'+PALETTE[idx%PALETTE.length]"
                                x-text="cap(PRODUCTS[idx].product)"></th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    {{-- LTV section --}}
                    <tr style="background:#F8FAFC;">
                        <td class="py-1.5 pl-1 text-xs font-bold text-slate-400 uppercase tracking-wider" colspan="99">Lifetime Value</td>
                    </tr>
                    <tr class="border-b border-slate-50">
                        <td class="py-2.5 text-xs font-medium text-slate-500">Avg LTV</td>
                        <template x-for="idx in selected" :key="'ltv'+idx">
                            <td class="py-2.5 px-3 text-right font-bold text-slate-800 tabular-nums"
                                x-text="fmtPeso(PRODUCTS[idx].avg_ltv)"></td>
                        </template>
                    </tr>
                    <tr class="border-b border-slate-50">
                        <td class="py-2.5 text-xs font-medium text-slate-500">Customers</td>
                        <template x-for="idx in selected" :key="'cu'+idx">
                            <td class="py-2.5 px-3 text-right text-slate-700 tabular-nums"
                                x-text="PRODUCTS[idx].customer_count.toLocaleString()"></td>
                        </template>
                    </tr>
                    <tr class="border-b border-slate-50">
                        <td class="py-2.5 text-xs font-medium text-slate-500">Avg Orders</td>
                        <template x-for="idx in selected" :key="'ao'+idx">
                            <td class="py-2.5 px-3 text-right text-slate-700 tabular-nums"
                                x-text="PRODUCTS[idx].avg_orders+'x'"></td>
                        </template>
                    </tr>
                    <tr class="border-b border-slate-50">
                        <td class="py-2.5 text-xs font-medium text-slate-500">Avg Order Value</td>
                        <template x-for="idx in selected" :key="'aov'+idx">
                            <td class="py-2.5 px-3 text-right text-slate-700 tabular-nums"
                                x-text="fmtPeso(PRODUCTS[idx].avg_order_value)"></td>
                        </template>
                    </tr>
                    <tr class="border-b border-slate-50">
                        <td class="py-2.5 text-xs font-medium text-slate-500">Repeat Rate</td>
                        <template x-for="idx in selected" :key="'rr'+idx">
                            <td class="py-2.5 px-3 text-right font-semibold tabular-nums"
                                :style="PRODUCTS[idx].repeat_rate>=50?'color:#059669':(PRODUCTS[idx].repeat_rate>=30?'color:#d97706':'color:#dc2626')"
                                x-text="PRODUCTS[idx].repeat_rate+'%'"></td>
                        </template>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2.5 text-xs font-medium text-slate-500">Total Revenue</td>
                        <template x-for="idx in selected" :key="'tr'+idx">
                            <td class="py-2.5 px-3 text-right text-slate-700 tabular-nums"
                                x-text="fmtPeso(PRODUCTS[idx].total_revenue)"></td>
                        </template>
                    </tr>
                    {{-- New Customers section --}}
                    <tr style="background:#F8FAFC;">
                        <td class="py-1.5 pl-1 text-xs font-bold text-slate-400 uppercase tracking-wider" colspan="99">New Customers</td>
                    </tr>
                    <tr class="border-b border-slate-50">
                        <td class="py-2.5 text-xs font-medium text-slate-500">New in {{ $lastMonthLabel }}</td>
                        <template x-for="idx in selected" :key="'nm'+idx">
                            <td class="py-2.5 px-3 text-right font-bold text-slate-800 tabular-nums"
                                x-text="PRODUCTS[idx].last_month.toLocaleString()"></td>
                        </template>
                    </tr>
                    <tr class="border-b border-slate-50">
                        <td class="py-2.5 text-xs font-medium text-slate-500">MoM Growth</td>
                        <template x-for="idx in selected" :key="'mg'+idx">
                            <td class="py-2.5 px-3 text-right font-semibold tabular-nums"
                                :style="PRODUCTS[idx].growth_rate===null?'color:#94A3B8':(PRODUCTS[idx].growth_rate>=0?'color:#059669':'color:#dc2626')"
                                x-text="PRODUCTS[idx].growth_rate===null?'—':(PRODUCTS[idx].growth_rate>=0?'+':'')+PRODUCTS[idx].growth_rate+'%'"></td>
                        </template>
                    </tr>
                    <tr class="border-b border-slate-50">
                        <td class="py-2.5 text-xs font-medium text-slate-500">Last 12 mo. total</td>
                        <template x-for="idx in selected" :key="'t1'+idx">
                            <td class="py-2.5 px-3 text-right text-slate-700 tabular-nums"
                                x-text="PRODUCTS[idx].total_12mo.toLocaleString()"></td>
                        </template>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-xs font-medium text-slate-500">1st Order RTS Rate</td>
                        <template x-for="idx in selected" :key="'rt'+idx">
                            <td class="py-2.5 px-3 text-right font-semibold tabular-nums"
                                :style="PRODUCTS[idx].rts_rate===null?'color:#94A3B8':(PRODUCTS[idx].rts_rate<=15?'color:#059669':(PRODUCTS[idx].rts_rate<=30?'color:#d97706':'color:#dc2626'))"
                                x-text="PRODUCTS[idx].rts_rate===null?'—':PRODUCTS[idx].rts_rate+'%'"></td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Cohort LTV chart --}}
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-slate-800">Cumulative LTV over 6 Months</h3>
            <p class="text-xs text-slate-400 mt-0.5">Revenue accumulated per customer in the months after their first purchase</p>
        </div>
        <div x-show="hasCohortData" style="position:relative;height:260px;">
            <canvas id="cohortChart"></canvas>
        </div>
        <div x-show="!hasCohortData" class="text-center py-8 text-slate-400 text-sm">
            Selected products need ≥10 customers each for cohort tracking.
        </div>
    </div>

    {{-- Monthly new customer trend chart --}}
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-slate-800">New Customer Trend — Last 12 Months</h3>
            <p class="text-xs text-slate-400 mt-0.5">Monthly count of first-time buyers per product</p>
        </div>
        <div style="position:relative;height:260px;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

</div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const PRODUCTS = @json($productData);
const COHORT   = @json($cohortLtv);
const MONTHS   = @json($months);

const COHORT_MAP = {};
COHORT.forEach(c => { COHORT_MAP[c.product] = c; });

const MN = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const MONTH_LABELS = MONTHS.map(m => { const p = m.split('-'); return MN[parseInt(p[1])-1]+' '+p[0]; });

const PALETTE = ['#2563EB','#f59e0b','#10b981','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'];

function fmtPeso(v) {
    if (v >= 1e6) return '₱' + (v/1e6).toFixed(1) + 'M';
    if (v >= 1e3) return '₱' + (v/1e3).toFixed(0) + 'K';
    return '₱' + Math.round(v).toLocaleString();
}

let cohortChart = null;
let trendChart  = null;

try { (function pcAppDef() {

window.pcApp = function() {
    return {
        selected:      [],
        hasCohortData: false,
        PALETTE,

        init() {},

        toggle(idx) {
            const pos = this.selected.indexOf(idx);
            if (pos === -1) { this.selected.push(idx); }
            else            { this.selected.splice(pos, 1); }
            this.$nextTick(() => this.updateCharts());
        },

        isSelected(idx) { return this.selected.includes(idx); },

        clearAll() {
            this.selected      = [];
            this.hasCohortData = false;
            if (cohortChart) { cohortChart.destroy(); cohortChart = null; }
            if (trendChart)  { trendChart.destroy();  trendChart  = null; }
        },

        cap(str) { return str.replace(/\b\w/g, l => l.toUpperCase()); },

        updateCharts() {
            this.updateCohortChart();
            this.updateTrendChart();
        },

        updateCohortChart() {
            const canvas = document.getElementById('cohortChart');
            if (!canvas) return;

            const datasets = this.selected
                .filter(i => COHORT_MAP[PRODUCTS[i].product])
                .map(i => {
                    const c = COHORT_MAP[PRODUCTS[i].product];
                    const color = PALETTE[i % PALETTE.length];
                    return {
                        label:           this.cap(PRODUCTS[i].product),
                        data:            [c.ltv_30, c.ltv_60, c.ltv_90, c.ltv_120, c.ltv_150, c.ltv_180],
                        borderColor:     color,
                        backgroundColor: color + '18',
                        borderWidth:     2.5,
                        pointRadius:     4,
                        pointHoverRadius:6,
                        tension:         0.4,
                        fill:            false,
                    };
                });

            this.hasCohortData = datasets.length > 0;
            if (!this.hasCohortData) {
                if (cohortChart) { cohortChart.destroy(); cohortChart = null; }
                return;
            }

            const labels = ['30 days','60 days','90 days','120 days','150 days','180 days'];
            if (cohortChart) {
                cohortChart.data.datasets = datasets;
                cohortChart.update('none');
            } else {
                cohortChart = new Chart(canvas, {
                    type: 'line',
                    data: { labels, datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12, usePointStyle: true } },
                            tooltip: { callbacks: { label: c => c.dataset.label + ': ' + fmtPeso(c.raw) } }
                        },
                        scales: {
                            x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                            y: { ticks: { font: { size: 10 }, callback: v => fmtPeso(v) }, grid: { color: '#f1f5f9' } }
                        }
                    }
                });
            }
        },

        updateTrendChart() {
            const canvas = document.getElementById('trendChart');
            if (!canvas) return;

            const datasets = this.selected.map(i => {
                const color = PALETTE[i % PALETTE.length];
                return {
                    label:           this.cap(PRODUCTS[i].product),
                    data:            PRODUCTS[i].monthly,
                    borderColor:     color,
                    backgroundColor: color + '18',
                    borderWidth:     2.5,
                    pointRadius:     3,
                    pointHoverRadius:5,
                    tension:         0.3,
                    fill:            false,
                };
            });

            if (trendChart) {
                trendChart.data.datasets = datasets;
                trendChart.update('none');
            } else {
                trendChart = new Chart(canvas, {
                    type: 'line',
                    data: { labels: MONTH_LABELS, datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12, usePointStyle: true } },
                            tooltip: { callbacks: { label: c => c.dataset.label + ': ' + c.raw.toLocaleString() + ' new' } }
                        },
                        scales: {
                            x: { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } },
                            y: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' },
                                 title: { display: true, text: 'New Customers', font: { size: 10 } } }
                        }
                    }
                });
            }
        }
    };
};

})(); } catch(e) { console.error('pcApp:', e); }
</script>
@endpush
