@extends('layouts.app')
@section('title', 'Product Comparison')
@section('subtitle', 'LTV, growth & acquisition quality — select products to compare')

@section('header_actions')
@php $ltvDateFrom = $dateFrom ?? ''; $ltvDateTo = $dateTo ?? ''; @endphp
<form method="GET" action="{{ route('analytics.ltv') }}" class="flex items-center gap-1.5 filter-form">
    <input type="hidden" name="date_from" class="filter-date-from" value="{{ $ltvDateFrom }}">
    <input type="hidden" name="date_to"   class="filter-date-to"   value="{{ $ltvDateTo }}">

    <div class="flex items-center gap-0.5 p-1 rounded-xl bg-white shadow-sm" style="border:1px solid #E2E8F0;">

        <div x-data="calPicker('{{ $ltvDateFrom }}', '{{ $ltvDateTo }}')"
             @keydown.escape.window="open = false"
             class="relative">
            <button type="button" @click="toggle()"
                    class="filter-icon-btn relative"
                    :class="hasFilter ? 'filter-icon-btn--active' : ''"
                    aria-label="Date filter" title="Date filter">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span x-show="hasFilter" x-cloak
                      class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full"
                      style="background:#F5A623; border:1.5px solid #fff;"></span>
            </button>

            <div x-show="open" x-cloak @click.outside="open = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                 class="absolute top-full mt-2 right-0 rounded-xl overflow-hidden origin-top-right"
                 style="width:660px; background:#fff; border:1px solid #D1D5DB; box-shadow:0 10px 40px -5px rgba(0,0,0,0.15); z-index:9999;">

                <div class="flex items-center gap-0 px-4 py-3" style="border-bottom:1px solid #F3F4F6;">
                    <div class="flex-1 flex flex-col" style="border-bottom:2px solid #2563EB; padding-bottom:4px;">
                        <span class="text-sm" :style="startStr ? 'color:#111827;' : 'color:#CBD5E1;'"
                              x-text="startStr ? startDisplay : 'DD/MM/YYYY'"></span>
                    </div>
                    <div class="flex-shrink-0 px-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#D1D5DB;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </div>
                    <div class="flex-1 flex flex-col" style="border-bottom:2px solid #E5E7EB; padding-bottom:4px;">
                        <span class="text-sm" :style="endStr ? 'color:#111827;' : 'color:#CBD5E1;'"
                              x-text="endStr ? endDisplay : 'DD/MM/YYYY'"></span>
                    </div>
                    <div class="flex items-center gap-2 pl-3 flex-shrink-0">
                        <button x-show="startStr" x-cloak type="button" @click="clearDates()"
                                class="text-xs font-medium cursor-pointer transition-colors"
                                style="color:#6B7280;"
                                onmouseover="this.style.color='#EF4444'"
                                onmouseout="this.style.color='#6B7280'">Clear</button>
                        <button type="button" @click="open = false"
                                class="w-7 h-7 flex items-center justify-center rounded cursor-pointer"
                                style="color:#9CA3AF;"
                                onmouseover="this.style.background='#F3F4F6'"
                                onmouseout="this.style.background=''">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex">
                    <div class="flex flex-col py-4 flex-shrink-0" style="width:152px; border-right:1px solid #F3F4F6;">
                        <template x-for="p in presets" :key="p.k">
                            <button type="button" @click="setPreset(p.k)"
                                    class="text-left text-sm transition-all cursor-pointer"
                                    :style="activePreset === p.k ? 'color:#111827;font-weight:600;background:#F9FAFB;border-left:3px solid #2563EB;padding:6px 12px 6px 9px;' : 'color:#6B7280;padding:6px 12px;'"
                                    x-text="p.l"></button>
                        </template>
                    </div>
                    <div class="flex-1 p-4" @mouseleave="hoverStr = ''">
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex gap-0">
                                        <button type="button" @click="prevYear()" class="cal-nav-btn">«</button>
                                        <button type="button" @click="prevMonth()" class="cal-nav-btn">‹</button>
                                    </div>
                                    <span class="text-sm font-bold select-none" style="color:#374151;" x-text="leftLabel"></span>
                                    <div style="width:46px;"></div>
                                </div>
                                <div class="grid grid-cols-7 mb-1">
                                    <div class="cal-dh">Su</div><div class="cal-dh">Mo</div><div class="cal-dh">Tu</div>
                                    <div class="cal-dh">We</div><div class="cal-dh">Th</div><div class="cal-dh">Fr</div><div class="cal-dh">Sa</div>
                                </div>
                                <div class="grid grid-cols-7">
                                    <template x-for="day in getDays(leftYear, leftMonth)" :key="'L'+day.s">
                                        <div class="cal-cell" :class="cellClass(day)">
                                            <button type="button" :disabled="!day.c" @click="day.c && selectDate(day.s)"
                                                    @mouseover="day.c && picking && (hoverStr = day.s)"
                                                    class="cal-btn" :class="btnClass(day)" x-text="day.d"></button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div style="width:46px;"></div>
                                    <span class="text-sm font-bold select-none" style="color:#374151;" x-text="rightLabel"></span>
                                    <div class="flex gap-0">
                                        <button type="button" @click="nextMonth()" class="cal-nav-btn">›</button>
                                        <button type="button" @click="nextYear()" class="cal-nav-btn">»</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-7 mb-1">
                                    <div class="cal-dh">Su</div><div class="cal-dh">Mo</div><div class="cal-dh">Tu</div>
                                    <div class="cal-dh">We</div><div class="cal-dh">Th</div><div class="cal-dh">Fr</div><div class="cal-dh">Sa</div>
                                </div>
                                <div class="grid grid-cols-7">
                                    <template x-for="day in getDays(rightYear, rightMonth)" :key="'R'+day.s">
                                        <div class="cal-cell" :class="cellClass(day)">
                                            <button type="button" :disabled="!day.c" @click="day.c && selectDate(day.s)"
                                                    @mouseover="day.c && picking && (hoverStr = day.s)"
                                                    class="cal-btn" :class="btnClass(day)" x-text="day.d"></button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="w-px h-5 mx-0.5 flex-shrink-0" style="background:#E2E8F0;"></div>

        <button type="submit" class="filter-icon-btn filter-icon-btn--apply" aria-label="Apply filters" title="Apply filters">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
        </button>
    </div>
</form>
@endsection

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
function calPicker(fromVal, toVal) {
    const MN = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const now = new Date();
    const initLeftMonth = now.getMonth() === 0 ? 11 : now.getMonth() - 1;
    const initLeftYear  = now.getMonth() === 0 ? now.getFullYear() - 1 : now.getFullYear();

    return {
        open:         false,
        startStr:     fromVal || '',
        endStr:       toVal   || '',
        hoverStr:     '',
        picking:      false,
        activePreset: '',
        leftYear:     initLeftYear,
        leftMonth:    initLeftMonth,

        presets: [
            {k:'today',        l:'Today'},
            {k:'yesterday',    l:'Yesterday'},
            {k:'7d',           l:'Last 7 days'},
            {k:'30d',          l:'Last 30 days'},
            {k:'90d',          l:'90 days ago'},
            {k:'last_month',   l:'Last month'},
            {k:'week_to_now',  l:'Early week to now'},
            {k:'month_to_now', l:'Early month to now'},
        ],

        get rightYear()  { return this.leftMonth === 11 ? this.leftYear + 1 : this.leftYear; },
        get rightMonth() { return this.leftMonth === 11 ? 0 : this.leftMonth + 1; },
        get hasFilter()  { return !!this.startStr; },
        get leftLabel()  { return MN[this.leftMonth]  + ' ' + this.leftYear; },
        get rightLabel() { return MN[this.rightMonth] + ' ' + this.rightYear; },
        get startDisplay() {
            if (!this.startStr) return '';
            const d = new Date(this.startStr + 'T00:00:00');
            return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
        },
        get endDisplay() {
            if (!this.endStr) return '';
            const d = new Date(this.endStr + 'T00:00:00');
            return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
        },

        ds(d) {
            return d.getFullYear() + '-'
                 + String(d.getMonth()+1).padStart(2,'0') + '-'
                 + String(d.getDate()).padStart(2,'0');
        },
        todayStr() { return this.ds(new Date()); },

        getDays(yr, mo) {
            const first    = new Date(yr, mo, 1);
            const total    = new Date(yr, mo + 1, 0).getDate();
            const startPad = first.getDay();
            const days     = [];
            for (let i = startPad - 1; i >= 0; i--) {
                const d = new Date(yr, mo, -i);
                days.push({s: this.ds(d), d: d.getDate(), c: false});
            }
            for (let i = 1; i <= total; i++) {
                days.push({s: this.ds(new Date(yr, mo, i)), d: i, c: true});
            }
            let n = 1;
            while (days.length < 42) {
                const d = new Date(yr, mo + 1, n++);
                days.push({s: this.ds(d), d: d.getDate(), c: false});
            }
            return days;
        },

        isStart(s)  { return !!this.startStr && s === this.startStr; },
        isEnd(s)    { return !!this.endStr   && s === this.endStr; },
        isToday(s)  { return s === this.todayStr(); },
        isInRange(s) {
            if (!this.startStr) return false;
            const e = this.endStr || this.hoverStr;
            if (!e) return false;
            const lo = this.startStr <= e ? this.startStr : e;
            const hi = this.startStr <= e ? e : this.startStr;
            return s > lo && s < hi;
        },

        dow(s) { return new Date(s + 'T00:00:00').getDay(); },

        cellClass(day) {
            if (!day.c) return '';
            const s     = day.s;
            const eStr  = this.endStr || (this.picking ? this.hoverStr : '');
            const start = this.isStart(s);
            const end   = !!eStr && s === eStr;
            const range = this.isInRange(s);
            if (!start && !end && !range) return '';
            if (start && end) return '';
            const d = this.dow(s);
            let cls = 'in-range';
            if (start || d === 0) cls += ' row-cap-l';
            if (end   || d === 6) cls += ' row-cap-r';
            return cls;
        },

        btnClass(day) {
            if (!day.c) return 'cal-btn-other';
            const s    = day.s;
            const eStr = this.endStr || (this.picking ? this.hoverStr : '');
            if (this.isStart(s) || (!!eStr && s === eStr)) return 'cal-btn-sel';
            if (this.isToday(s)) return 'cal-btn-today';
            return '';
        },

        selectDate(s) {
            if (!this.startStr || this.endStr) {
                this.startStr     = s;
                this.endStr       = '';
                this.picking      = true;
                this.activePreset = '';
            } else {
                if (s < this.startStr) {
                    this.endStr   = this.startStr;
                    this.startStr = s;
                } else {
                    this.endStr = s;
                }
                this.picking  = false;
                this.hoverStr = '';
                this.applyDates();
            }
        },

        setPreset(type) {
            const t   = this.todayStr();
            const ago = n => { const d = new Date(); d.setDate(d.getDate()-n); return this.ds(d); };
            const now2 = new Date();
            switch (type) {
                case 'today':        this.startStr = t;      this.endStr = t;          break;
                case 'yesterday':    this.startStr = ago(1); this.endStr = ago(1);     break;
                case '7d':           this.startStr = ago(6); this.endStr = t;          break;
                case '30d':          this.startStr = ago(29);this.endStr = t;          break;
                case '90d':          this.startStr = ago(89);this.endStr = t;          break;
                case 'last_month': {
                    const f = new Date(now2.getFullYear(), now2.getMonth()-1, 1);
                    const l = new Date(now2.getFullYear(), now2.getMonth(), 0);
                    this.startStr = this.ds(f); this.endStr = this.ds(l); break;
                }
                case 'week_to_now': {
                    const m = new Date(now2);
                    const dow = m.getDay() || 7;
                    m.setDate(m.getDate() - dow + 1);
                    this.startStr = this.ds(m); this.endStr = t; break;
                }
                case 'month_to_now': {
                    const f = new Date(now2.getFullYear(), now2.getMonth(), 1);
                    this.startStr = this.ds(f); this.endStr = t; break;
                }
            }
            this.activePreset = type;
            this.picking      = false;
            this.hoverStr     = '';
            this.applyDates();
        },

        applyDates() {
            const form = this.$el.closest('form');
            if (form) {
                const fi = form.querySelector('.filter-date-from');
                const ti = form.querySelector('.filter-date-to');
                if (fi) fi.value = this.startStr;
                if (ti) ti.value = this.endStr;
            }
            this.open = false;
            this.$nextTick(() => {
                const f = this.$el.closest('form');
                if (f) f.requestSubmit();
            });
        },

        clearDates() {
            this.startStr     = '';
            this.endStr       = '';
            this.hoverStr     = '';
            this.picking      = false;
            this.activePreset = '';
            const form = this.$el.closest('form');
            if (form) {
                const fi = form.querySelector('.filter-date-from');
                const ti = form.querySelector('.filter-date-to');
                if (fi) fi.value = '';
                if (ti) ti.value = '';
            }
            this.open = false;
            this.$nextTick(() => {
                const f = this.$el.closest('form');
                if (f) f.requestSubmit();
            });
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                const n = new Date();
                if (this.startStr) {
                    const d = new Date(this.startStr + 'T00:00:00');
                    this.leftYear  = d.getFullYear();
                    this.leftMonth = d.getMonth();
                } else {
                    this.leftMonth = n.getMonth() === 0 ? 11 : n.getMonth() - 1;
                    this.leftYear  = n.getMonth() === 0 ? n.getFullYear() - 1 : n.getFullYear();
                }
            }
        },

        prevMonth() { if(this.leftMonth===0){this.leftMonth=11;this.leftYear--;}else{this.leftMonth--;} },
        nextMonth() { if(this.leftMonth===11){this.leftMonth=0;this.leftYear++;}else{this.leftMonth++;} },
        prevYear()  { this.leftYear--; },
        nextYear()  { this.leftYear++; },
    };
}
</script>
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
