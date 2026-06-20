{{--
    Reusable filter bar partial.
    Required vars: $filterRoute, $products, $productFilter, $datePreset, $dateFrom, $dateTo
    Optional var:  $productParamName (default: 'product_filter')
                   $extraFilters     (raw HTML slot for extra controls, e.g. status select)
--}}
@php $productParam = $productParamName ?? 'product_filter'; @endphp

<form method="GET" action="{{ $filterRoute }}" class="flex items-center gap-2 filter-form">
    <input type="hidden" name="date_from" class="filter-date-from" value="{{ $dateFrom ?? '' }}">
    <input type="hidden" name="date_to"   class="filter-date-to"   value="{{ $dateTo ?? '' }}">

    {{-- Date filter pill --}}
    <div class="flex items-center rounded-lg border border-slate-200 bg-white shadow-sm divide-x divide-slate-200 overflow-hidden">
        <div class="flex items-center gap-1.5 px-2.5 py-1.5">
            <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <select class="filter-preset text-xs bg-transparent border-0 outline-none text-slate-700 font-semibold cursor-pointer pr-1" onchange="handlePreset(this)">
                <option value="all"    {{ ($datePreset ?? '') === 'all'    ? 'selected' : '' }}>All Time</option>
                <option value="7d"     {{ ($datePreset ?? '') === '7d'     ? 'selected' : '' }}>Last 7 days</option>
                <option value="30d"    {{ ($datePreset ?? '') === '30d'    ? 'selected' : '' }}>Last 30 days</option>
                <option value="90d"    {{ ($datePreset ?? '') === '90d'    ? 'selected' : '' }}>Last 90 days</option>
                <option value="custom" {{ ($datePreset ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
        </div>
        <div class="filter-custom flex items-center gap-1.5 px-2.5 py-1.5 {{ ($datePreset ?? '') !== 'custom' ? 'hidden' : '' }}">
            <input type="date" class="text-xs bg-transparent border-0 outline-none text-slate-600 cursor-pointer"
                   value="{{ $dateFrom ?? '' }}"
                   onchange="this.closest('.filter-form').querySelector('.filter-date-from').value=this.value">
            <span class="text-slate-300 text-xs">→</span>
            <input type="date" class="text-xs bg-transparent border-0 outline-none text-slate-600 cursor-pointer"
                   value="{{ $dateTo ?? '' }}"
                   onchange="this.closest('.filter-form').querySelector('.filter-date-to').value=this.value">
        </div>
    </div>

    {{-- Searchable product filter --}}
    <div class="relative"
         x-data="{
             open: false,
             search: '',
             selected: '{{ addslashes($productFilter ?? '') }}',
             products: {{ Js::from($products ?? []) }},
             dropPos: 'top:0;right:0',
             get filteredProducts() {
                 if (!this.search) return this.products;
                 const q = this.search.toLowerCase();
                 return this.products.filter(p => p.toLowerCase().includes(q));
             },
             get selectedLabel() {
                 return this.selected || 'All Products';
             },
             select(val) {
                 this.selected = val;
                 this.open = false;
                 this.search = '';
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

        {{-- Trigger --}}
        <button type="button" @click="toggle()"
                x-ref="trigger"
                aria-label="Filter by product"
                :aria-expanded="open"
                class="relative flex items-center gap-2 text-xs rounded-lg px-3 py-1.5 font-semibold transition-all duration-200 shadow-sm border cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-1"
                :class="selected ? 'border-amber-400 text-slate-900 shadow-md focus:ring-amber-400' : 'border-slate-800 text-white hover:border-slate-600 focus:ring-slate-700'"
                :style="selected ? 'background:#F5A623;' : 'background:#0F172A;'">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span class="max-w-[140px] truncate" x-text="selectedLabel"></span>
            <span x-show="selected" class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full border-2 border-white" style="background:#EF4444;"></span>
            <svg class="w-3 h-3 flex-shrink-0 transition-transform duration-200" :class="{'rotate-180': open}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Dropdown panel — teleported to <body> to escape all stacking contexts --}}
        <template x-teleport="body">
        <div x-show="open" x-cloak @click.outside="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-y-95 -translate-y-1"
             x-transition:enter-end="opacity-100 scale-y-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-y-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-y-95 -translate-y-1"
             class="fixed w-72 rounded-2xl z-[9999] overflow-hidden origin-top-right"
             :style="dropPos + ';z-index:9999;background:#0F172A;border:1px solid #1E293B;box-shadow:0 25px 50px -12px rgba(0,0,0,0.6),0 0 0 1px rgba(245,166,35,0.1)'"
             style="display:none;z-index:9999;">

            {{-- Header --}}
            <div class="px-4 pt-5 pb-4" style="background:linear-gradient(135deg,#0F172A 0%,#1a2540 100%); border-bottom:1px solid #1E293B;">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0" style="background:rgba(245,166,35,0.15);">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#F5A623;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold" style="color:#F5A623; letter-spacing:0.05em;">FILTER BY PRODUCT</p>
                            <p class="text-xs" style="color:#475569;"
                               x-text="selected ? 'Showing 1 product only' : '{{ count($products ?? []) }} products total'"></p>
                        </div>
                    </div>
                    <button x-show="selected" type="button" @click.stop="select('')"
                            aria-label="Clear product filter"
                            class="flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-lg transition-all duration-150 cursor-pointer"
                            style="color:#F87171; background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.2);"
                            onmouseover="this.style.background='rgba(248,113,113,0.2)'"
                            onmouseout="this.style.background='rgba(248,113,113,0.1)'">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        Clear
                    </button>
                </div>
                <div class="flex items-center gap-2 px-3 py-2 rounded-xl transition-all duration-150"
                     style="background:#1E293B; border:1.5px solid #334155;"
                     x-bind:style="search.length > 0 ? 'border-color:#F5A623; box-shadow:0 0 0 3px rgba(245,166,35,0.12)' : 'border-color:#334155'">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         :style="search.length > 0 ? 'color:#F5A623' : 'color:#475569'">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="search" x-ref="searchInput"
                           placeholder="Search products…"
                           autocomplete="off"
                           class="text-xs border-0 outline-none w-full"
                           style="background:#1E293B; color:#E2E8F0; caret-color:#F5A623;"
                           @click.stop>
                    <button x-show="search" @click.stop="search = ''" type="button" aria-label="Clear search"
                            class="flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center cursor-pointer transition-colors"
                            style="background:#334155; color:#64748B;"
                            onmouseover="this.style.background='#475569'; this.style.color='#E2E8F0'"
                            onmouseout="this.style.background='#334155'; this.style.color='#64748B'">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>

            {{-- Options --}}
            <div class="overflow-y-auto" style="max-height:220px; scrollbar-width:thin; scrollbar-color:#1E293B transparent;">
                <button type="button" @click="select('')"
                        class="w-full text-left px-4 py-2.5 flex items-center gap-3 cursor-pointer transition-all duration-100"
                        :style="selected === '' ? 'background:rgba(245,166,35,0.1);' : ''"
                        onmouseover="this.style.background = this.getAttribute('data-sel')==='1' ? 'rgba(245,166,35,0.15)' : 'rgba(255,255,255,0.03)'"
                        onmouseout="this.style.background = this.getAttribute('data-sel')==='1' ? 'rgba(245,166,35,0.1)' : ''"
                        :data-sel="selected === '' ? '1' : '0'">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 transition-all duration-150"
                         :style="selected === '' ? 'background:#F5A623; box-shadow:0 0 8px rgba(245,166,35,0.4)' : 'background:#1E293B; border:1.5px solid #334155'">
                        <svg x-show="selected === ''" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" style="color:#0F172A;">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold" :style="selected === '' ? 'color:#F5A623' : 'color:#94A3B8'">All Products</span>
                    <span class="ml-auto text-xs rounded-md px-1.5 py-0.5 font-mono tabular-nums" style="background:#1E293B; color:#475569; font-size:10px;">{{ count($products ?? []) }}</span>
                </button>
                <div style="height:1px; background:#1E293B; margin:0 16px;"></div>
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
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-3" style="background:#1E293B;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#334155;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs font-medium" style="color:#475569;">No match for</p>
                    <p class="text-xs font-bold mt-0.5" style="color:#64748B;" x-text='"«" + search + "»"'></p>
                    <button type="button" @click.stop="search = ''" class="mt-2 text-xs font-medium cursor-pointer" style="color:#F5A623;">Clear search</button>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-4 py-2.5 flex items-center justify-between" style="background:#080E1A; border-top:1px solid #1E293B;">
                <span class="text-xs tabular-nums" style="color:#334155;"
                      x-text="search ? filteredProducts.length + ' of {{ count($products ?? []) }} results' : '{{ count($products ?? []) }} products'"></span>
                <span x-show="selected" class="flex items-center gap-1 text-xs font-semibold" style="color:#10B981;">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Filter active
                </span>
            </div>
        </div>
        </template>

        <input type="hidden" name="{{ $productParam }}" :value="selected">
    </div>

    {{-- Extra filters slot (e.g. map status select) --}}
    @isset($extraFilters)
        {!! $extraFilters !!}
    @endisset

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
