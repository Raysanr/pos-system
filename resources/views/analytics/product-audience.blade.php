@extends('layouts.app')
@section('title', 'Product Audience')
@section('subtitle', $selectedProduct ? 'Demographic profile for ' . $selectedProduct : 'Select a product to see audience insights')

@section('header_actions')
<form method="GET" action="{{ route('analytics.product-audience') }}" class="flex items-center gap-2 filter-form" id="audienceFilterForm">
    <input type="hidden" name="date_from" class="filter-date-from" value="{{ $dateFrom ?? '' }}">
    <input type="hidden" name="date_to"   class="filter-date-to"   value="{{ $dateTo ?? '' }}">

    {{-- Date pill --}}
    <div class="flex items-center rounded-lg border border-slate-200 bg-white shadow-sm divide-x divide-slate-200 overflow-hidden">
        <div class="flex items-center gap-1.5 px-2.5 py-1.5">
            <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <select class="filter-preset text-xs bg-transparent border-0 outline-none text-slate-700 font-semibold cursor-pointer pr-1" onchange="handlePreset(this)">
                <option value="all"    {{ $datePreset === 'all'    ? 'selected' : '' }}>All Time</option>
                <option value="7d"     {{ $datePreset === '7d'     ? 'selected' : '' }}>Last 7 days</option>
                <option value="30d"    {{ $datePreset === '30d'    ? 'selected' : '' }}>Last 30 days</option>
                <option value="90d"    {{ $datePreset === '90d'    ? 'selected' : '' }}>Last 90 days</option>
                <option value="custom" {{ $datePreset === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
        </div>
        <div class="filter-custom flex items-center gap-1.5 px-2.5 py-1.5 {{ $datePreset !== 'custom' ? 'hidden' : '' }}">
            <input type="date" class="text-xs bg-transparent border-0 outline-none text-slate-600 cursor-pointer" value="{{ $dateFrom ?? '' }}"
                   onchange="this.closest('.filter-form').querySelector('.filter-date-from').value=this.value">
            <span class="text-slate-300 text-xs">→</span>
            <input type="date" class="text-xs bg-transparent border-0 outline-none text-slate-600 cursor-pointer" value="{{ $dateTo ?? '' }}"
                   onchange="this.closest('.filter-form').querySelector('.filter-date-to').value=this.value">
        </div>
    </div>

    {{-- Product picker — auto-submits on select (product is required for this page) --}}
    <div class="relative"
         x-data="{
             open: false,
             search: '',
             selected: '{{ addslashes($selectedProduct ?? '') }}',
             products: {{ Js::from($products) }},
             get filteredProducts() {
                 if (!this.search) return this.products;
                 const q = this.search.toLowerCase();
                 return this.products.filter(p => p.toLowerCase().includes(q));
             },
             select(val) {
                 this.selected = val;
                 this.open = false;
                 this.search = '';
                 this.$nextTick(() => this.$el.closest('form').submit());
             },
             toggle() {
                 this.open = !this.open;
                 if (this.open) this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
             }
         }"
         @keydown.escape.window="open = false">

        <button type="button" @click="toggle()"
                aria-label="Select product"
                :aria-expanded="open"
                class="relative flex items-center gap-2 text-xs rounded-lg px-3 py-1.5 font-semibold transition-all duration-200 shadow-sm border cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-1"
                :class="selected ? 'border-amber-400 text-slate-900 shadow-md focus:ring-amber-400' : 'border-slate-800 text-white hover:border-slate-600 focus:ring-slate-700'"
                :style="selected ? 'background:#F5A623;' : 'background:#0F172A;'">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span class="max-w-[160px] truncate" x-text="selected || 'Select Product'"></span>

            {{-- Active indicator dot --}}
            <span x-show="selected"
                  class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full border-2 border-white"
                  style="background:#EF4444;"></span>

            <svg class="w-3 h-3 flex-shrink-0 transition-transform duration-200" :class="{'rotate-180': open}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="open" x-cloak @click.outside="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-y-95 -translate-y-1"
             x-transition:enter-end="opacity-100 scale-y-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-y-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-y-95 -translate-y-1"
             class="absolute right-0 top-full mt-2 w-72 rounded-2xl z-[9999] overflow-hidden origin-top-right"
             style="background:#0F172A; border:1px solid #1E293B; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6), 0 0 0 1px rgba(245,166,35,0.1);">

            <div class="px-4 pt-5 pb-4" style="background:linear-gradient(135deg,#0F172A 0%,#1a2540 100%); border-bottom:1px solid #1E293B;">
                <div class="flex items-center gap-2 mb-2.5">
                    <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background:rgba(245,166,35,0.15);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#F5A623;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold" style="color:#F5A623; letter-spacing:0.05em;">SELECT PRODUCT</p>
                        <p class="text-xs" style="color:#475569;">Click to load audience data</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 px-3 py-2 rounded-xl transition-all duration-150"
                     style="background:#1E293B; border:1.5px solid #334155;"
                     x-bind:style="search.length > 0 ? 'border-color:#F5A623; box-shadow:0 0 0 3px rgba(245,166,35,0.12)' : 'border-color:#334155'">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         :style="search.length > 0 ? 'color:#F5A623' : 'color:#475569'">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="search" x-ref="searchInput" placeholder="Search products…"
                           autocomplete="off"
                           class="text-xs border-0 outline-none w-full"
                           style="background:#1E293B; color:#E2E8F0; caret-color:#F5A623;" @click.stop>
                    <button x-show="search" @click.stop="search = ''" type="button" aria-label="Clear search"
                            class="flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center cursor-pointer"
                            style="background:#334155; color:#64748B;"
                            onmouseover="this.style.background='#475569'; this.style.color='#E2E8F0'"
                            onmouseout="this.style.background='#334155'; this.style.color='#64748B'">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto" style="max-height:240px; scrollbar-width:thin; scrollbar-color:#1E293B transparent;">
                <template x-for="product in filteredProducts" :key="product">
                    <button type="button" @click="select(product)"
                            class="w-full text-left px-4 py-2.5 flex items-center gap-3 cursor-pointer transition-all duration-100"
                            :style="selected === product ? 'background:rgba(245,166,35,0.1);' : ''"
                            onmouseover="this.style.background = this.getAttribute('data-sel')==='1' ? 'rgba(245,166,35,0.15)' : 'rgba(255,255,255,0.03)'"
                            onmouseout="this.style.background = this.getAttribute('data-sel')==='1' ? 'rgba(245,166,35,0.1)' : ''"
                            :data-sel="selected === product ? '1' : '0'">
                        <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 transition-all duration-150"
                             :style="selected === product ? 'background:#F5A623; box-shadow:0 0 8px rgba(245,166,35,0.4)' : 'background:#1E293B; border:1.5px solid #334155'">
                            <svg x-show="selected === product" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" style="color:#0F172A;">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-xs truncate" :style="selected === product ? 'color:#F5A623; font-weight:600' : 'color:#94A3B8'" x-text="product"></span>
                    </button>
                </template>
                <div x-show="filteredProducts.length === 0 && search.length > 0" class="px-4 py-7 text-center">
                    <p class="text-xs font-medium" style="color:#475569;">No match for</p>
                    <p class="text-xs font-bold mt-0.5" style="color:#64748B;" x-text='"«" + search + "»"'></p>
                    <button type="button" @click.stop="search = ''" class="mt-2 text-xs font-medium cursor-pointer" style="color:#F5A623;">Clear search</button>
                </div>
            </div>

            <div class="px-4 py-2.5 flex items-center justify-between" style="background:#080E1A; border-top:1px solid #1E293B;">
                <span class="text-xs tabular-nums" style="color:#334155;"
                      x-text="search ? filteredProducts.length + ' of {{ count($products) }} results' : '{{ count($products) }} products'"></span>
                <span class="text-xs" style="color:#475569;">Click to apply instantly</span>
            </div>
        </div>

        <input type="hidden" name="product" :value="selected">
    </div>

    {{-- Apply for date changes --}}
    <button type="submit"
            class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500"
            style="background:#1E40AF; color:#fff; border:1px solid #1E40AF;"
            onmouseover="this.style.background='#1d3a9e'" onmouseout="this.style.background='#1E40AF'">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        Apply
    </button>
</form>
@endsection

@section('content')

@if(!$selectedProduct)
<div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(245,166,35,0.1);">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#F5A623;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
    </div>
    <p class="text-base font-semibold text-gray-700">No products found</p>
    <p class="text-sm text-gray-400 mt-1">Sync your orders first to see product audience data.</p>
</div>
@elseif($data)

{{-- KPI row --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @php
        $kpis = [
            ['label' => 'Total Orders',   'value' => number_format($data['kpis']['total_orders']),  'sub' => 'for this product', 'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
            ['label' => 'Average Age',    'value' => $data['kpis']['avg_age'] ? $data['kpis']['avg_age'] . ' yrs' : '—',        'sub' => 'from order notes',  'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            ['label' => 'Delivery Rate',  'value' => $data['kpis']['deliver_rate'] . '%',           'sub' => 'orders delivered', 'icon' => 'M5 13l4 4L19 7'],
            ['label' => 'RTS Rate',       'value' => $data['kpis']['rts_rate'] . '%',               'sub' => 'returned to sender','icon' => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6'],
        ];
    @endphp
    @foreach($kpis as $k)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ $k['label'] }}</span>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(245,166,35,0.1);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#F5A623;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $k['icon'] }}"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 font-display">{{ $k['value'] }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ $k['sub'] }}</p>
    </div>
    @endforeach
</div>

{{-- Row 1: Age Groups + Gender --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-1 font-display">Age Groups</h3>
        <p class="text-xs text-gray-400 mb-4">Distribution of customer ages (from order notes)</p>
        @if(array_sum($data['ageGroups']['data']) > 0)
            <canvas id="ageChart" height="200"></canvas>
        @else
            <div class="flex items-center justify-center h-40 text-sm text-gray-400">No age data found in notes yet — run the backfill command.</div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-1 font-display">Gender Split</h3>
        <p class="text-xs text-gray-400 mb-4">From customer profiles</p>
        @if(array_sum($data['gender']['data']) > 0)
            <div class="flex items-center justify-center">
                <canvas id="genderChart" style="max-height:220px; max-width:220px;"></canvas>
            </div>
            <div class="flex justify-center gap-4 mt-3 flex-wrap">
                @foreach($data['gender']['labels'] as $i => $label)
                @php $colors = ['#F5A623','#0F172A','#94A3B8']; @endphp
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $colors[$i % 3] }};"></span>
                    <span class="text-xs text-gray-600">{{ $label }} ({{ number_format($data['gender']['data'][$i]) }})</span>
                </div>
                @endforeach
            </div>
        @else
            <div class="flex items-center justify-center h-40 text-sm text-gray-400">No gender data available.</div>
        @endif
    </div>

</div>

{{-- Row 2: Health Conditions + Top Provinces --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-1 font-display">Health Conditions</h3>
        <p class="text-xs text-gray-400 mb-4">Extracted from order notes</p>
        @if(count($data['healthConditions']['labels']) > 0)
            <canvas id="healthChart" height="220"></canvas>
        @else
            <div class="flex items-center justify-center h-40 text-sm text-gray-400">No health conditions found in notes yet.</div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-1 font-display">Top Provinces</h3>
        <p class="text-xs text-gray-400 mb-4">Orders by province (top 15)</p>
        @if(count($data['provinces']['labels']) > 0)
            <canvas id="provincesChart" height="220"></canvas>
        @else
            <div class="flex items-center justify-center h-40 text-sm text-gray-400">No province data available.</div>
        @endif
    </div>

</div>

{{-- Row 3: New vs Returning + Top Customers --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-1 font-display">New vs Repeat Buyers</h3>
        <p class="text-xs text-gray-400 mb-4">Customers who bought this product once vs. multiple times</p>
        @php $nvr = $data['newVsReturning']; @endphp
        @if($nvr['new'] + $nvr['returning'] > 0)
            <div class="flex items-center justify-center">
                <canvas id="nvrChart" style="max-height:200px; max-width:200px;"></canvas>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-4">
                <div class="rounded-lg p-3 text-center" style="background:rgba(245,166,35,0.08); border:1px solid rgba(245,166,35,0.2);">
                    <p class="text-xl font-bold font-display" style="color:#F5A623;">{{ number_format($nvr['new']) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">First-time buyers</p>
                </div>
                <div class="rounded-lg p-3 text-center" style="background:rgba(15,23,42,0.05); border:1px solid rgba(15,23,42,0.15);">
                    <p class="text-xl font-bold font-display" style="color:#0F172A;">{{ number_format($nvr['returning']) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Repeat buyers</p>
                </div>
            </div>
        @else
            <div class="flex items-center justify-center h-40 text-sm text-gray-400">No data available.</div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-1 font-display">Top Customers</h3>
        <p class="text-xs text-gray-400 mb-4">By total spent on this product</p>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <th class="text-left pb-2 font-semibold text-gray-400 uppercase tracking-wide">Name</th>
                        <th class="text-left pb-2 font-semibold text-gray-400 uppercase tracking-wide">Province</th>
                        <th class="text-right pb-2 font-semibold text-gray-400 uppercase tracking-wide">Orders</th>
                        <th class="text-right pb-2 font-semibold text-gray-400 uppercase tracking-wide">Spent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($data['topCustomers'] as $i => $c)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-2 pr-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                     style="background:{{ $i < 3 ? 'rgba(245,166,35,0.15)' : '#F1F5F9' }}; color:{{ $i < 3 ? '#F5A623' : '#64748B' }};">
                                    {{ $i + 1 }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $c->name ?? '—' }}</p>
                                    <p class="text-gray-400">{{ $c->gender ?? '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-2 text-gray-500">{{ $c->province ?? '—' }}</td>
                        <td class="py-2 text-right font-medium text-gray-700">{{ $c->order_count }}</td>
                        <td class="py-2 text-right font-semibold" style="color:#F5A623;">₱{{ number_format($c->spent) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="py-6 text-center text-gray-400">No customers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endif {{-- end if $data --}}

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
@if($data)
const GOLD  = '#F5A623';
const DARK  = '#0F172A';
const SLATE = '#94A3B8';

const barDefaults = {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#64748B' } },
        y: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 }, color: '#64748B' }, beginAtZero: true },
    },
};

// Age Groups
@if(array_sum($data['ageGroups']['data']) > 0)
new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: @json($data['ageGroups']['labels']),
        datasets: [{
            data: @json($data['ageGroups']['data']),
            backgroundColor: 'rgba(245,166,35,0.85)',
            borderRadius: 6,
            borderSkipped: false,
        }],
    },
    options: barDefaults,
});
@endif

// Gender
@if(array_sum($data['gender']['data']) > 0)
new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: @json($data['gender']['labels']),
        datasets: [{
            data: @json($data['gender']['data']),
            backgroundColor: [GOLD, DARK, SLATE],
            borderWidth: 0,
            hoverOffset: 4,
        }],
    },
    options: {
        cutout: '65%',
        plugins: { legend: { display: false } },
    },
});
@endif

// Health Conditions
@if(count($data['healthConditions']['labels']) > 0)
new Chart(document.getElementById('healthChart'), {
    type: 'bar',
    data: {
        labels: @json($data['healthConditions']['labels']),
        datasets: [{
            data: @json($data['healthConditions']['data']),
            backgroundColor: 'rgba(15,23,42,0.8)',
            borderRadius: 4,
            borderSkipped: false,
        }],
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 }, color: '#64748B' }, beginAtZero: true },
            y: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#374151' } },
        },
    },
});
@endif

// Provinces
@if(count($data['provinces']['labels']) > 0)
new Chart(document.getElementById('provincesChart'), {
    type: 'bar',
    data: {
        labels: @json($data['provinces']['labels']),
        datasets: [{
            data: @json($data['provinces']['data']),
            backgroundColor: 'rgba(245,166,35,0.75)',
            borderRadius: 4,
            borderSkipped: false,
        }],
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 10 }, color: '#64748B' }, beginAtZero: true },
            y: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#374151' } },
        },
    },
});
@endif

// New vs Returning
@php $nvr = $data['newVsReturning']; @endphp
@if($nvr['new'] + $nvr['returning'] > 0)
new Chart(document.getElementById('nvrChart'), {
    type: 'doughnut',
    data: {
        labels: ['First-time', 'Repeat buyers'],
        datasets: [{
            data: [{{ $nvr['new'] }}, {{ $nvr['returning'] }}],
            backgroundColor: [GOLD, DARK],
            borderWidth: 0,
            hoverOffset: 4,
        }],
    },
    options: {
        cutout: '60%',
        plugins: {
            legend: { display: true, position: 'bottom', labels: { font: { size: 11 }, color: '#64748B', boxWidth: 12 } },
        },
    },
});
@endif

@endif {{-- $data --}}
</script>
@endpush
