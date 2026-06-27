@extends('layouts.app')
@section('title', 'Return Reasons')
@section('subtitle', 'Click a reason to see which product or province is responsible')

@section('header_actions')
    @include('partials.filter-bar', [
        'filterRoute'   => route('analytics.return-reasons'),
        'products'      => $products,
        'productFilter' => $productFilter,
        'datePreset'    => $datePreset,
        'dateFrom'      => $dateFrom,
        'dateTo'        => $dateTo,
    ])
@endsection

@section('content')

<div x-data="rrApp()" x-init="init()">

{{-- ── KPI strip ──────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-5">
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Total Returned</div>
        <div class="text-2xl font-bold text-slate-800">{{ number_format($totalReturned) }}</div>
        <div class="text-xs text-slate-400 mt-0.5">orders with return reason</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Unique Reasons</div>
        <div class="text-2xl font-bold text-slate-800">{{ $uniqueReasons }}</div>
        <div class="text-xs text-slate-400 mt-0.5">distinct return reasons</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Top Reason</div>
        @if($topReason)
        <div class="text-base font-bold text-slate-800 leading-snug">{{ $topReason['reason'] }}</div>
        <div class="text-sm font-bold mt-0.5" style="color:#ef4444;">{{ $topReason['pct'] }}% of returns</div>
        @else
        <div class="text-2xl font-bold text-slate-300">—</div>
        @endif
    </div>
</div>

@if($totalReturned === 0)
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-16 text-center">
        <svg class="w-12 h-12 mx-auto mb-3 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm font-medium text-slate-400">No returned orders with reasons found.</p>
        <p class="text-xs text-slate-300 mt-1">Return reasons are stored when Pancake syncs returned orders.</p>
    </div>
@else

{{-- ── Reason list ─────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 mb-5">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800">Return Reasons</h3>
            <p class="text-xs text-slate-400 mt-0.5">Click a reason to drill into which product or province is responsible</p>
        </div>
        <button x-show="selectedReason !== null" x-cloak
                @click="selectedReason = null"
                class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors cursor-pointer flex-shrink-0"
                style="color:#64748B;background:#F1F5F9;"
                onmouseover="this.style.background='#E2E8F0'" onmouseout="this.style.background='#F1F5F9'">
            Show all
        </button>
    </div>

    <div class="space-y-2">
        @foreach($overview as $i => $r)
        @php $barW = $overview[0]['count'] > 0 ? round($r['count'] / $overview[0]['count'] * 100) : 0; @endphp
        <div @click="selectReason({{ $i }})"
             class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-all"
             :style="selectedReason === {{ $i }}
                 ? 'background:#FEF3C7; border:1.5px solid #F59E0B;'
                 : 'background:#F8FAFC; border:1.5px solid transparent;'"
             onmouseover="if(this.getAttribute('data-sel')!=='1') this.style.background='#F1F5F9'"
             onmouseout="if(this.getAttribute('data-sel')!=='1') this.style.background='#F8FAFC'"
             :data-sel="selectedReason === {{ $i }} ? '1' : '0'">

            <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold"
                 style="background:#FEE2E2; color:#EF4444;">{{ $i + 1 }}</div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-slate-700 truncate">{{ $r['reason'] }}</span>
                    <span class="text-xs font-bold text-slate-500 ml-3 flex-shrink-0 tabular-nums">
                        {{ number_format($r['count']) }} <span class="text-slate-300 font-normal">·</span> {{ $r['pct'] }}%
                    </span>
                </div>
                <div class="bg-slate-100 rounded-full h-1.5">
                    <div class="h-1.5 rounded-full transition-all" style="width:{{ $barW }}%;background:#EF4444;"></div>
                </div>
            </div>

            <svg class="w-4 h-4 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 :style="selectedReason === {{ $i }} ? 'color:#F59E0B;transform:rotate(90deg)' : 'color:#CBD5E1'">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Drill-down panel ────────────────────────────────────────────────── --}}
<div x-show="selectedReason !== null" x-cloak class="space-y-4">

    <div class="flex items-center gap-2">
        <div class="h-px flex-1 bg-slate-100"></div>
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-2">
            Who returns for: <span class="text-slate-600" x-text="selectedReasonLabel"></span>
        </span>
        <div class="h-px flex-1 bg-slate-100"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- By Product --}}
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-1">By Product</h3>
            <p class="text-xs text-slate-400 mb-4">Which product gets this return reason most?</p>
            <div x-show="productRows.length > 0" style="position:relative;height:260px;">
                <canvas id="productChart"></canvas>
            </div>
            <div x-show="productRows.length === 0" class="text-center py-10 text-slate-300 text-xs">No product data for this reason</div>
        </div>

        {{-- By Province --}}
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-1">By Province</h3>
            <p class="text-xs text-slate-400 mb-4">Is this concentrated in certain provinces?</p>
            <div x-show="provinceRows.length > 0" style="position:relative;height:260px;">
                <canvas id="provinceChart"></canvas>
            </div>
            <div x-show="provinceRows.length === 0" class="text-center py-10 text-slate-300 text-xs">No province data for this reason</div>
        </div>

    </div>

    {{-- Insight box --}}
    <div x-show="insight !== null" x-cloak
         class="rounded-xl border p-4 flex items-start gap-3"
         style="background:#FFFBEB; border-color:#FDE68A;">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="#D97706" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs text-amber-800 font-medium leading-relaxed" x-text="insight"></p>
    </div>

</div>

{{-- Empty state when nothing selected --}}
<div x-show="selectedReason === null" class="text-center py-10 text-slate-300">
    <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/>
    </svg>
    <p class="text-sm font-medium text-slate-400">Click a reason above to drill down</p>
</div>

@endif

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const BY_PRODUCT  = @json($byProduct);
const BY_PROVINCE = @json($byProvince);
const OVERVIEW    = @json($overview);

let productChart  = null;
let provinceChart = null;

function cap(str) {
    if (!str) return '';
    return str.replace(/\b\w/g, l => l.toUpperCase());
}

function buildHBar(canvasId, labels, data, color, existingChart) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return existingChart;

    if (existingChart) { existingChart.destroy(); }

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: color + 'CC',
                borderColor:     color,
                borderWidth:     1.5,
                borderRadius:    4,
            }]
        },
        options: {
            indexAxis:           'y',
            responsive:          true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' ' + c.raw.toLocaleString() + ' returns' } }
            },
            scales: {
                x: { ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' }, beginAtZero: true },
                y: { ticks: { font: { size: 10 } }, grid: { display: false } }
            }
        }
    });
}

try { (function rrAppDef() {

window.rrApp = function() {
    return {
        selectedReason:      null,
        selectedReasonLabel: '',
        productRows:         [],
        provinceRows:        [],
        insight:             null,

        init() {},

        selectReason(idx) {
            if (this.selectedReason === idx) {
                this.selectedReason      = null;
                this.selectedReasonLabel = '';
                this.productRows         = [];
                this.provinceRows        = [];
                this.insight             = null;
                if (productChart)  { productChart.destroy();  productChart  = null; }
                if (provinceChart) { provinceChart.destroy(); provinceChart = null; }
                return;
            }

            this.selectedReason      = idx;
            this.selectedReasonLabel = OVERVIEW[idx].reason;

            const reason = OVERVIEW[idx].reason;

            this.productRows  = BY_PRODUCT .filter(r => r.reason === reason).slice(0, 12);
            this.provinceRows = BY_PROVINCE.filter(r => r.reason === reason).slice(0, 12);

            this.$nextTick(() => {
                this.buildCharts();
                this.buildInsight(reason);
            });
        },

        buildCharts() {
            if (this.productRows.length > 0) {
                productChart = buildHBar(
                    'productChart',
                    this.productRows.map(r => cap(r.product)),
                    this.productRows.map(r => r.count),
                    '#2563EB',
                    productChart
                );
            }
            if (this.provinceRows.length > 0) {
                provinceChart = buildHBar(
                    'provinceChart',
                    this.provinceRows.map(r => r.province),
                    this.provinceRows.map(r => r.count),
                    '#10b981',
                    provinceChart
                );
            }
        },

        buildInsight(reason) {
            if (this.provinceRows.length === 0) {
                this.insight = null;
                return;
            }

            const topProvince   = this.provinceRows[0];
            const provinceTotal = this.provinceRows.reduce((s, r) => s + r.count, 0);
            const provinceConc  = provinceTotal > 0 ? topProvince.count / provinceTotal : 0;

            if (provinceConc > 0.5 && this.provinceRows.length > 1) {
                this.insight = topProvince.province + ' accounts for ' + Math.round(provinceConc * 100) + '% of "' + reason + '" returns — this is a province-level problem, not a product issue.';
            } else if (this.provinceRows.length <= 2) {
                this.insight = '"' + reason + '" is concentrated in just ' + this.provinceRows.length + ' province(s) — consider targeting delivery quality or follow-up in those areas.';
            } else {
                this.insight = '"' + reason + '" is spread across many provinces — likely a product or customer expectation issue rather than a delivery location problem.';
            }
        }
    };
};

})(); } catch(e) { console.error('rrApp:', e); }
</script>
@endpush
