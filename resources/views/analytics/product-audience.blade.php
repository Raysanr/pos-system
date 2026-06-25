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

    {{-- Product picker --}}
    <div class="relative"
         x-data="{
             open: false,
             search: '',
             selected: '{{ addslashes($selectedProduct ?? '') }}',
             products: {{ Js::from($products) }},
             dropPos: 'top:0;right:0',
             get filteredProducts() {
                 if (!this.search) return this.products;
                 const q = this.search.toLowerCase();
                 return this.products.filter(p => p.toLowerCase().includes(q));
             },
             select(val) {
                 this.selected = val;
                 this.open = false;
                 this.search = '';
                 this.$nextTick(() => this.$el.closest('form').requestSubmit());
             },
             toggle() {
                 this.open = !this.open;
                 if (this.open) this.$nextTick(() => {
                     const r = this.$refs.trigger.getBoundingClientRect();
                     this.dropPos = 'top:' + (r.bottom + 8) + 'px;right:' + (window.innerWidth - r.right) + 'px';
                     this.$refs.searchInput && this.$refs.searchInput.focus();
                 });
             }
         }"
         @keydown.escape.window="open = false">

        <button type="button" @click.stop="toggle()"
                x-ref="trigger"
                aria-label="Select product"
                :aria-expanded="open"
                class="relative flex items-center gap-2 text-xs rounded-lg px-3 py-1.5 font-semibold transition-all duration-200 shadow-sm border cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-1"
                :class="selected ? 'border-amber-400 text-slate-900 shadow-md focus:ring-amber-400' : 'border-slate-800 text-white hover:border-slate-600 focus:ring-slate-700'"
                :style="selected ? 'background:#F5A623;' : 'background:#0F172A;'">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span class="max-w-[160px] truncate" x-text="selected || 'Select Product'"></span>
            <span x-show="selected" class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full border-2 border-white" style="background:#EF4444;"></span>
            <svg class="w-3 h-3 flex-shrink-0 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Dropdown panel — teleported to <body> to escape header stacking context --}}
        <template x-teleport="body">
        <div x-show="open" x-cloak @click.outside="open = false" @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-y-95 -translate-y-1"
             x-transition:enter-end="opacity-100 scale-y-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-y-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-y-95 -translate-y-1"
             class="fixed w-72 rounded-2xl z-[9999] overflow-hidden origin-top-right"
             :style="dropPos + ';z-index:9999;background:#0F172A;border:1px solid #1E293B;box-shadow:0 25px 50px -12px rgba(0,0,0,0.6),0 0 0 1px rgba(245,166,35,0.1)'"
             style="display:none;z-index:9999;">

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
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" :style="search.length > 0 ? 'color:#F5A623' : 'color:#475569'">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="search" x-ref="searchInput" placeholder="Search products…"
                           autocomplete="off" class="text-xs border-0 outline-none w-full"
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
        </template>

        <input type="hidden" name="product" :value="selected">
    </div>

    {{-- Apply --}}
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

{{-- No-product state --}}
<div id="content-no-product" @if($selectedProduct) hidden @endif>
<div class="flex flex-col items-center justify-center py-32 text-center">
    <div class="w-20 h-20 rounded-2xl flex items-center justify-center mb-5" style="background:rgba(245,166,35,0.08); border:1px solid rgba(245,166,35,0.15);">
        <svg style="width:36px;height:36px;color:#F5A623;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
        </svg>
    </div>
    <p class="text-base font-semibold" style="color:#334155;">Choose a product to get started</p>
    <p class="text-sm mt-1.5" style="color:#94A3B8;">Select a product from the filter above to view its audience data.</p>
</div>
</div>

{{-- Data state --}}
<div id="content-data" @if(!$selectedProduct || !$data) hidden @endif>

{{-- KPI Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    @php
    $kpiDefs = $data ? [
        ['label'=>'Total Orders',   'value'=>number_format($data['kpis']['total_orders']),                           'sub'=>'for this product',    'color'=>'#1E40AF', 'bg'=>'rgba(30,64,175,0.08)',   'border'=>'#DBEAFE',
         'icon'=>'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
        ['label'=>'Average Age',    'value'=>$data['kpis']['avg_age'] ? $data['kpis']['avg_age'].' yrs' : '—',      'sub'=>'from order notes',    'color'=>'#8B5CF6', 'bg'=>'rgba(139,92,246,0.08)',  'border'=>'#EDE9FE',
         'icon'=>'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['label'=>'Delivery Rate',  'value'=>$data['kpis']['deliver_rate'].'%',                                      'sub'=>'orders delivered',    'color'=>'#10B981', 'bg'=>'rgba(16,185,129,0.08)',  'border'=>'#D1FAE5',
         'icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label'=>'RTS Rate',       'value'=>$data['kpis']['rts_rate'].'%',                                          'sub'=>'returned to sender',  'color'=>'#EF4444', 'bg'=>'rgba(239,68,68,0.08)',   'border'=>'#FEE2E2',
         'icon'=>'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6'],
        ['label'=>'Bottles Sold',   'value'=>number_format($data['kpis']['total_bottles']),                          'sub'=>'delivered bottles',   'color'=>'#10B981', 'bg'=>'rgba(16,185,129,0.08)',  'border'=>'#D1FAE5',
         'icon'=>'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
    ] : [];
    @endphp
    @foreach($kpiDefs as $k)
    <div class="kpi-card bg-white rounded-xl shadow-sm overflow-hidden" style="border:1px solid {{ $k['border'] }}; border-left:4px solid {{ $k['color'] }};">
        <div class="p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold uppercase tracking-wider" style="color:#64748B;">{{ $k['label'] }}</p>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:{{ $k['bg'] }};">
                    <svg style="width:18px;height:18px;color:{{ $k['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $k['icon'] }}"/>
                    </svg>
                </div>
            </div>
            <p data-kpi="{{ $loop->index }}" class="text-3xl font-bold font-mono" style="color:{{ $k['color'] }};">{{ $k['value'] }}</p>
            <p class="text-xs mt-1.5" style="color:#94A3B8;">{{ $k['sub'] }}</p>
        </div>
    </div>
    @endforeach
</div>

{{-- Row 1: Age Groups + Gender --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Age Groups</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">Distribution of customer ages (from order notes)</p>
        <div id="age-chart-section" @if(!$data || array_sum($data['ageGroups']['data']) <= 0) hidden @endif>
            <div class="chart-wrap" style="min-height:180px;">
                <div class="chart-skeleton" id="sk-age">
                    <div style="display:flex;align-items:flex-end;gap:10px;height:160px;padding-top:20px;">
                        @foreach([40,60,80,70,50,30] as $h)
                        <div class="sk-bar" style="flex:1;height:{{ $h }}%;"></div>
                        @endforeach
                    </div>
                </div>
                <canvas id="ageChart" class="chart-canvas" height="180"></canvas>
            </div>
        </div>
        <div id="age-empty-section" @if($data && array_sum($data['ageGroups']['data']) > 0) hidden @endif>
            <div class="empty-state" style="min-height:160px;">
                <div class="empty-state-icon">
                    <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <p class="title">No age data yet</p>
                <p class="hint">Run <code style="font-family:'Fira Code',monospace;font-size:11px;background:#F1F5F9;padding:1px 4px;border-radius:3px;">app:extract-order-demographics</code> to extract age from notes.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Gender Split</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">From customer profiles</p>
        <div id="gender-chart-section" @if(!$data || array_sum($data['gender']['data']) <= 0) hidden @endif>
            <div class="chart-wrap" style="min-height:180px;">
                <div class="chart-skeleton" id="sk-gender">
                    <div class="sk-bar" style="width:140px;height:140px;border-radius:50%;margin:0 auto;"></div>
                </div>
                <div class="flex items-center justify-center">
                    <canvas id="genderChart" class="chart-canvas" style="max-height:200px;max-width:200px;"></canvas>
                </div>
                <div id="gender-legend" class="flex justify-center gap-4 mt-3 flex-wrap">
                    @if($data && array_sum($data['gender']['data']) > 0)
                    @foreach($data['gender']['labels'] as $i => $label)
                    @php $gColors = ['#F5A623','#0F172A','#94A3B8']; @endphp
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $gColors[$i % 3] }};"></span>
                        <span class="text-xs" style="color:#475569;">{{ $label }} ({{ number_format($data['gender']['data'][$i]) }})</span>
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
        </div>
        <div id="gender-empty-section" @if($data && array_sum($data['gender']['data']) > 0) hidden @endif>
            <div class="empty-state" style="min-height:160px;">
                <div class="empty-state-icon">
                    <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <p class="title">No gender data</p>
                <p class="hint">Gender data comes from customer profiles synced from Pancake.</p>
            </div>
        </div>
    </div>

</div>

{{-- Row 2: Health Conditions + Top Provinces --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Health Conditions</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">Extracted from order notes</p>
        <div id="health-chart-section" @if(!$data || count($data['healthConditions']['labels']) <= 0) hidden @endif>
            <div class="chart-wrap" style="min-height:220px;">
                <div class="chart-skeleton" id="sk-health">
                    <div style="display:flex;flex-direction:column;gap:10px;padding:4px 0;">
                        @foreach([85,55,40,25] as $w)
                        <div class="sk-bar" style="width:{{ $w }}%;height:22px;"></div>
                        @endforeach
                    </div>
                </div>
                <canvas id="healthChart" class="chart-canvas" height="220"></canvas>
            </div>
        </div>
        <div id="health-empty-section" @if($data && count($data['healthConditions']['labels']) > 0) hidden @endif>
            <div class="empty-state" style="min-height:180px;">
                <div class="empty-state-icon">
                    <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="title">No health conditions found</p>
                <p class="hint">Run <code style="font-family:'Fira Code',monospace;font-size:11px;background:#F1F5F9;padding:1px 4px;border-radius:3px;">app:extract-order-demographics</code> to extract from notes.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Top Provinces</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">Orders by province (top 15)</p>
        <div id="prov-chart-section" @if(!$data || count($data['provinces']['labels']) <= 0) hidden @endif>
            <div class="chart-wrap" style="min-height:220px;">
                <div class="chart-skeleton" id="sk-prov">
                    <div style="display:flex;flex-direction:column;gap:10px;padding:4px 0;">
                        @foreach([100,80,65,55,45,35,25] as $w)
                        <div class="sk-bar" style="width:{{ $w }}%;height:20px;"></div>
                        @endforeach
                    </div>
                </div>
                <canvas id="provincesChart" class="chart-canvas" height="220"></canvas>
            </div>
        </div>
        <div id="prov-empty-section" @if($data && count($data['provinces']['labels']) > 0) hidden @endif>
            <div class="empty-state" style="min-height:180px;">
                <div class="empty-state-icon">
                    <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                </div>
                <p class="title">No province data</p>
                <p class="hint">Sync orders to populate location data.</p>
            </div>
        </div>
    </div>

</div>

{{-- Row 3: New vs Repeat + Top Customers --}}
@php
    $nvr     = $data ? $data['newVsReturning'] : ['new' => 0, 'returning' => 0];
    $nvrTotal = $nvr['new'] + $nvr['returning'];
@endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- New vs Repeat Buyers --}}
    <div class="bg-white rounded-xl shadow-sm p-5 flex flex-col" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">New vs Repeat Buyers</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">Customers who bought this product once vs. multiple times</p>

        <div id="nvr-chart-section" class="flex-1 flex flex-col items-center justify-center gap-5" @if($nvr['new'] + $nvr['returning'] <= 0) hidden @endif>
            {{-- Donut --}}
            <div class="chart-wrap" style="width:180px;height:180px;flex-shrink:0;">
                <div class="chart-skeleton" id="sk-nvr" style="display:flex;align-items:center;justify-content:center;">
                    <div class="sk-bar" style="width:160px;height:160px;border-radius:50%;"></div>
                </div>
                <canvas id="nvrChart" class="chart-canvas" style="max-height:180px;max-width:180px;"></canvas>
            </div>
            {{-- Stat boxes --}}
            <div class="grid grid-cols-2 gap-3 w-full">
                <div class="rounded-xl p-3.5 text-center" style="background:rgba(245,166,35,0.07);border:1px solid rgba(245,166,35,0.22);">
                    <p id="nvr-stat-new" class="text-2xl font-bold font-mono" style="color:#F5A623;">{{ number_format($nvr['new']) }}</p>
                    <p class="text-xs font-semibold mt-1" style="color:#334155;">First-time</p>
                    <p id="nvr-stat-new-pct" class="text-xs mt-0.5" style="color:#94A3B8;">{{ $nvrTotal > 0 ? round($nvr['new'] / $nvrTotal * 100) : 0 }}% of buyers</p>
                </div>
                <div class="rounded-xl p-3.5 text-center" style="background:rgba(30,64,175,0.07);border:1px solid rgba(30,64,175,0.18);">
                    <p id="nvr-stat-returning" class="text-2xl font-bold font-mono" style="color:#1E40AF;">{{ number_format($nvr['returning']) }}</p>
                    <p class="text-xs font-semibold mt-1" style="color:#334155;">Repeat buyers</p>
                    <p id="nvr-stat-ret-pct" class="text-xs mt-0.5" style="color:#94A3B8;">{{ $nvrTotal > 0 ? round($nvr['returning'] / $nvrTotal * 100) : 0 }}% of buyers</p>
                </div>
            </div>
        </div>

        <div id="nvr-empty-section" class="flex-1" @if($nvr['new'] + $nvr['returning'] > 0) hidden @endif>
            <div class="empty-state" style="min-height:180px;">
                <div class="empty-state-icon">
                    <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <p class="title">No buyer data</p>
                <p class="hint">Sync orders to see new vs. repeat buyer breakdown.</p>
            </div>
        </div>
    </div>

    {{-- Top Customers --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden flex flex-col" style="border:1px solid #DBEAFE;">
        <div class="px-5 py-4 flex-none" style="border-bottom:1px solid #F1F5F9;">
            <h3 class="text-sm font-semibold" style="color:#1E293B;">Top Customers</h3>
            <p class="text-xs mt-0.5" style="color:#94A3B8;">By total spent on this product</p>
        </div>

        <div id="customers-table-section" class="overflow-y-auto flex-1" style="max-height:520px;" @if(!$data || count((array)$data['topCustomers']) <= 0) hidden @endif>
            <table class="w-full text-xs">
                <thead class="sticky top-0">
                    <tr style="background:#F8FAFC; border-bottom:1px solid #F1F5F9;">
                        <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wide" style="color:#94A3B8;">#</th>
                        <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wide" style="color:#94A3B8;">Name</th>
                        <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wide" style="color:#94A3B8;">Province</th>
                        <th class="px-3 py-2.5 text-right font-semibold uppercase tracking-wide" style="color:#94A3B8;">Orders</th>
                        <th class="px-3 py-2.5 text-right font-semibold uppercase tracking-wide" style="color:#94A3B8;">Spent</th>
                    </tr>
                </thead>
                <tbody id="tbody-audience-customers">
                    @if($data)
                    @foreach($data['topCustomers'] as $i => $c)
                    <tr style="border-top:1px solid #F8FAFC; transition:background 0.12s;"
                        onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                        <td class="px-3 py-2.5">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold font-mono"
                                 style="background:{{ $i < 3 ? 'rgba(245,166,35,0.12)' : '#F1F5F9' }}; color:{{ $i < 3 ? '#D97706' : '#64748B' }};">
                                {{ $i + 1 }}
                            </div>
                        </td>
                        <td class="px-3 py-2.5">
                            <p class="font-semibold" style="color:#1E293B;">{{ $c->name ?? '—' }}</p>
                            <p style="color:#94A3B8;">{{ $c->gender ?? '' }}</p>
                        </td>
                        <td class="px-3 py-2.5" style="color:#64748B;">{{ $c->province ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-right font-mono" style="color:#475569;">{{ $c->order_count }}</td>
                        <td class="px-3 py-2.5 text-right font-mono font-bold" style="color:#F5A623;">₱{{ number_format($c->spent) }}</td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <div id="customers-empty-section" class="flex-1" @if($data && count((array)$data['topCustomers']) > 0) hidden @endif>
            <div class="empty-state" style="min-height:180px;">
                <div class="empty-state-icon">
                    <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p class="title">No customers found</p>
                <p class="hint">No orders found for this product yet.</p>
            </div>
        </div>
    </div>

</div>

</div>{{-- /content-data --}}

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const GOLD   = '#F5A623';
const BLUE   = '#1E40AF';
const LBLUE  = '#3B82F6';
const SLATE  = '#94A3B8';
const DARK   = '#0F172A';
const GREEN  = '#10B981';
const VIOLET = '#8B5CF6';

const gridColor = '#F1F5F9';
const tickFont  = { size: 11, family: "'Fira Code', monospace" };
const tickColor = '#64748B';

window.__charts = window.__charts || {};

function revealChart(canvasId, skeletonId) {
    var canvas = document.getElementById(canvasId);
    var sk     = document.getElementById(skeletonId);
    if (canvas) canvas.classList.add('loaded');
    if (sk) sk.style.display = 'none';
}

function showSection(chartId, emptyId, hasData) {
    var chartEl = document.getElementById(chartId);
    var emptyEl = document.getElementById(emptyId);
    if (chartEl) chartEl.hidden = !hasData;
    if (emptyEl) emptyEl.hidden = !!hasData;
}

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

@if($data)
// Age Groups
@if(array_sum($data['ageGroups']['data']) > 0)
window.__charts.age = new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: @json($data['ageGroups']['labels']),
        datasets: [{ data: @json($data['ageGroups']['data']), backgroundColor: GOLD, borderRadius: 6, borderSkipped: false }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString()} customers` } } },
        scales: {
            x: { grid: { display: false }, ticks: { font: tickFont, color: tickColor } },
            y: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor }, beginAtZero: true }
        }
    }
});
revealChart('ageChart', 'sk-age');
@endif

// Gender
@if(array_sum($data['gender']['data']) > 0)
window.__charts.gender = new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: @json($data['gender']['labels']),
        datasets: [{ data: @json($data['gender']['data']), backgroundColor: [GOLD, DARK, SLATE], borderWidth: 0, hoverOffset: 5 }]
    },
    options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw.toLocaleString()}` } } } }
});
revealChart('genderChart', 'sk-gender');
@endif

// Health Conditions
@if(count($data['healthConditions']['labels']) > 0)
window.__charts.health = new Chart(document.getElementById('healthChart'), {
    type: 'bar',
    data: {
        labels: @json($data['healthConditions']['labels']),
        datasets: [{ data: @json($data['healthConditions']['data']), backgroundColor: DARK, borderRadius: 4, borderSkipped: false }]
    },
    options: {
        indexAxis: 'y', responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString()} orders` } } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor }, beginAtZero: true },
            y: { grid: { display: false }, ticks: { font: { size: 11, family: "'Fira Sans', sans-serif" }, color: '#374151' } }
        }
    }
});
revealChart('healthChart', 'sk-health');
@endif

// Provinces
@if(count($data['provinces']['labels']) > 0)
window.__charts.provinces = new Chart(document.getElementById('provincesChart'), {
    type: 'bar',
    data: {
        labels: @json($data['provinces']['labels']),
        datasets: [{ data: @json($data['provinces']['data']), backgroundColor: GOLD, borderRadius: 4, borderSkipped: false }]
    },
    options: {
        indexAxis: 'y', responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString()} orders` } } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor }, beginAtZero: true },
            y: { grid: { display: false }, ticks: { font: { size: 10, family: "'Fira Sans', sans-serif" }, color: '#374151' } }
        }
    }
});
revealChart('provincesChart', 'sk-prov');
@endif

// New vs Returning
@php $nvr = $data['newVsReturning']; @endphp
@if($nvr['new'] + $nvr['returning'] > 0)
window.__charts.nvr = new Chart(document.getElementById('nvrChart'), {
    type: 'doughnut',
    data: {
        labels: ['First-time', 'Repeat buyers'],
        datasets: [{ data: [{{ $nvr['new'] }}, {{ $nvr['returning'] }}], backgroundColor: [GOLD, BLUE], borderWidth: 0, hoverOffset: 5 }]
    },
    options: {
        cutout: '65%',
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw.toLocaleString()}` } } }
    }
});
revealChart('nvrChart', 'sk-nvr');
@endif

@endif

window.__ajaxFilterUpdate = async function (form, params, url) {
    var noProductEl = document.getElementById('content-no-product');
    var dataEl      = document.getElementById('content-data');

    // If currently on the "no product" screen, selecting a product needs a full render
    if (noProductEl && !noProductEl.hidden) {
        window.location.href = url;
        return;
    }

    var res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    var json = await res.json();
    var d    = json.data;

    if (!d) {
        if (noProductEl) noProductEl.hidden = false;
        if (dataEl)      dataEl.hidden = true;
        return;
    }

    if (noProductEl) noProductEl.hidden = true;
    if (dataEl)      dataEl.hidden = false;

    // KPIs
    var kpiValues = [
        Number(d.kpis.total_orders).toLocaleString(),
        d.kpis.avg_age ? d.kpis.avg_age + ' yrs' : '—',
        d.kpis.deliver_rate + '%',
        d.kpis.rts_rate + '%',
        Number(d.kpis.total_bottles).toLocaleString(),
    ];
    document.querySelectorAll('[data-kpi]').forEach(function (el) {
        var idx = parseInt(el.getAttribute('data-kpi'));
        if (kpiValues[idx] !== undefined) el.textContent = kpiValues[idx];
    });

    // Age chart
    var hasAge = d.ageGroups.data.reduce(function (a, b) { return a + b; }, 0) > 0;
    showSection('age-chart-section', 'age-empty-section', hasAge);
    if (hasAge) {
        if (window.__charts.age) {
            window.__charts.age.data.labels = d.ageGroups.labels;
            window.__charts.age.data.datasets[0].data = d.ageGroups.data;
            window.__charts.age.update();
        } else {
            window.__charts.age = new Chart(document.getElementById('ageChart'), {
                type: 'bar',
                data: { labels: d.ageGroups.labels, datasets: [{ data: d.ageGroups.data, backgroundColor: GOLD, borderRadius: 6, borderSkipped: false }] },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.raw.toLocaleString() + ' customers'; } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: tickFont, color: tickColor } },
                        y: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor }, beginAtZero: true }
                    }
                }
            });
            revealChart('ageChart', 'sk-age');
        }
    } else {
        if (window.__charts.age) { window.__charts.age.destroy(); window.__charts.age = null; }
    }

    // Gender chart
    var hasGender = d.gender.data.reduce(function (a, b) { return a + b; }, 0) > 0;
    showSection('gender-chart-section', 'gender-empty-section', hasGender);
    if (hasGender) {
        if (window.__charts.gender) {
            window.__charts.gender.data.labels = d.gender.labels;
            window.__charts.gender.data.datasets[0].data = d.gender.data;
            window.__charts.gender.update();
        } else {
            window.__charts.gender = new Chart(document.getElementById('genderChart'), {
                type: 'doughnut',
                data: { labels: d.gender.labels, datasets: [{ data: d.gender.data, backgroundColor: [GOLD, DARK, SLATE], borderWidth: 0, hoverOffset: 5 }] },
                options: { cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.raw.toLocaleString(); } } } } }
            });
            revealChart('genderChart', 'sk-gender');
        }
        var gColors   = ['#F5A623', '#0F172A', '#94A3B8'];
        var legendEl  = document.getElementById('gender-legend');
        if (legendEl) {
            legendEl.innerHTML = d.gender.labels.map(function (label, i) {
                return '<div class="flex items-center gap-1.5">'
                    + '<span class="w-2.5 h-2.5 rounded-full" style="background:' + gColors[i % 3] + ';"></span>'
                    + '<span class="text-xs" style="color:#475569;">' + escHtml(label) + ' (' + Number(d.gender.data[i]).toLocaleString() + ')</span>'
                    + '</div>';
            }).join('');
        }
    } else {
        if (window.__charts.gender) { window.__charts.gender.destroy(); window.__charts.gender = null; }
    }

    // Health chart
    var hasHealth = d.healthConditions.labels.length > 0;
    showSection('health-chart-section', 'health-empty-section', hasHealth);
    if (hasHealth) {
        if (window.__charts.health) {
            window.__charts.health.data.labels = d.healthConditions.labels;
            window.__charts.health.data.datasets[0].data = d.healthConditions.data;
            window.__charts.health.update();
        } else {
            window.__charts.health = new Chart(document.getElementById('healthChart'), {
                type: 'bar',
                data: { labels: d.healthConditions.labels, datasets: [{ data: d.healthConditions.data, backgroundColor: DARK, borderRadius: 4, borderSkipped: false }] },
                options: {
                    indexAxis: 'y', responsive: true,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.raw.toLocaleString() + ' orders'; } } } },
                    scales: {
                        x: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor }, beginAtZero: true },
                        y: { grid: { display: false }, ticks: { font: { size: 11, family: "'Fira Sans', sans-serif" }, color: '#374151' } }
                    }
                }
            });
            revealChart('healthChart', 'sk-health');
        }
    } else {
        if (window.__charts.health) { window.__charts.health.destroy(); window.__charts.health = null; }
    }

    // Provinces chart
    var hasProv = d.provinces.labels.length > 0;
    showSection('prov-chart-section', 'prov-empty-section', hasProv);
    if (hasProv) {
        if (window.__charts.provinces) {
            window.__charts.provinces.data.labels = d.provinces.labels;
            window.__charts.provinces.data.datasets[0].data = d.provinces.data;
            window.__charts.provinces.update();
        } else {
            window.__charts.provinces = new Chart(document.getElementById('provincesChart'), {
                type: 'bar',
                data: { labels: d.provinces.labels, datasets: [{ data: d.provinces.data, backgroundColor: GOLD, borderRadius: 4, borderSkipped: false }] },
                options: {
                    indexAxis: 'y', responsive: true,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.raw.toLocaleString() + ' orders'; } } } },
                    scales: {
                        x: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor }, beginAtZero: true },
                        y: { grid: { display: false }, ticks: { font: { size: 10, family: "'Fira Sans', sans-serif" }, color: '#374151' } }
                    }
                }
            });
            revealChart('provincesChart', 'sk-prov');
        }
    } else {
        if (window.__charts.provinces) { window.__charts.provinces.destroy(); window.__charts.provinces = null; }
    }

    // NVR chart + stat boxes
    var hasNvr = (d.newVsReturning.new + d.newVsReturning.returning) > 0;
    showSection('nvr-chart-section', 'nvr-empty-section', hasNvr);
    if (hasNvr) {
        if (window.__charts.nvr) {
            window.__charts.nvr.data.datasets[0].data = [d.newVsReturning.new, d.newVsReturning.returning];
            window.__charts.nvr.update();
        } else {
            window.__charts.nvr = new Chart(document.getElementById('nvrChart'), {
                type: 'doughnut',
                data: { labels: ['First-time', 'Repeat buyers'], datasets: [{ data: [d.newVsReturning.new, d.newVsReturning.returning], backgroundColor: [GOLD, BLUE], borderWidth: 0, hoverOffset: 5 }] },
                options: {
                    cutout: '65%',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.raw.toLocaleString(); } } } }
                }
            });
            revealChart('nvrChart', 'sk-nvr');
        }
        var nvrTot = d.newVsReturning.new + d.newVsReturning.returning;
        var nEl  = document.getElementById('nvr-stat-new');
        var rEl  = document.getElementById('nvr-stat-returning');
        var npEl = document.getElementById('nvr-stat-new-pct');
        var rpEl = document.getElementById('nvr-stat-ret-pct');
        if (nEl)  nEl.textContent  = Number(d.newVsReturning.new).toLocaleString();
        if (rEl)  rEl.textContent  = Number(d.newVsReturning.returning).toLocaleString();
        if (npEl) npEl.textContent = (nvrTot > 0 ? Math.round(d.newVsReturning.new / nvrTot * 100) : 0) + '% of buyers';
        if (rpEl) rpEl.textContent = (nvrTot > 0 ? Math.round(d.newVsReturning.returning / nvrTot * 100) : 0) + '% of buyers';
    } else {
        if (window.__charts.nvr) { window.__charts.nvr.destroy(); window.__charts.nvr = null; }
    }

    // Top customers table
    var hasCustomers = d.topCustomers && d.topCustomers.length > 0;
    showSection('customers-table-section', 'customers-empty-section', hasCustomers);
    if (hasCustomers) {
        var tbody = document.getElementById('tbody-audience-customers');
        if (tbody) {
            tbody.innerHTML = d.topCustomers.map(function (c, i) {
                return '<tr style="border-top:1px solid #F8FAFC; transition:background 0.12s;" onmouseover="this.style.background=\'#F0F7FF\'" onmouseout="this.style.background=\'\'">'
                    + '<td class="px-3 py-2.5"><div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold font-mono" style="background:' + (i < 3 ? 'rgba(245,166,35,0.12)' : '#F1F5F9') + '; color:' + (i < 3 ? '#D97706' : '#64748B') + ';">' + (i + 1) + '</div></td>'
                    + '<td class="px-3 py-2.5"><p class="font-semibold" style="color:#1E293B;">' + escHtml(c.name || '—') + '</p><p style="color:#94A3B8;">' + escHtml(c.gender || '') + '</p></td>'
                    + '<td class="px-3 py-2.5" style="color:#64748B;">' + escHtml(c.province || '—') + '</td>'
                    + '<td class="px-3 py-2.5 text-right font-mono" style="color:#475569;">' + Number(c.order_count).toLocaleString() + '</td>'
                    + '<td class="px-3 py-2.5 text-right font-mono font-bold" style="color:#F5A623;">₱' + Number(c.spent).toLocaleString() + '</td>'
                    + '</tr>';
            }).join('');
        }
    }
};
</script>
@endpush
