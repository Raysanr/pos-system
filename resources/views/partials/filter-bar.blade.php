{{--
    Icon-only filter toolbar with dual-calendar date range picker
    Required: $filterRoute, $products, $productFilter
    Optional: $datePreset, $dateFrom, $dateTo, $showDateFilter (default true)
              $productParamName, $extraFilters
--}}
@php
    $productParam   = $productParamName ?? 'product_filter';
    $showDateFilter = $showDateFilter ?? true;
@endphp

<form method="GET" action="{{ $filterRoute }}" class="flex items-center gap-1.5 filter-form">
    @if($showDateFilter)
    <input type="hidden" name="date_from" class="filter-date-from" value="{{ $dateFrom ?? '' }}">
    <input type="hidden" name="date_to"   class="filter-date-to"   value="{{ $dateTo ?? '' }}">
    @endif
    <input type="hidden" name="{{ $productParam }}" id="product-hidden-input" value="{{ $productFilter ?? '' }}">

    <div class="flex items-center gap-0.5 p-1 rounded-xl bg-white shadow-sm" style="border:1px solid #E2E8F0;">

        {{-- ── Date range picker ──────────────────────────────────────────── --}}
        @if($showDateFilter)
        <div x-data="calPicker('{{ $dateFrom ?? '' }}', '{{ $dateTo ?? '' }}')"
             @keydown.escape.window="open = false"
             class="relative">

            {{-- Trigger --}}
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

            {{-- Calendar panel --}}
            <div x-show="open" x-cloak
                 @click.outside="open = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                 class="absolute top-full mt-2 right-0 rounded-xl overflow-hidden origin-top-right"
                 style="width:660px; background:#fff; border:1px solid #D1D5DB; box-shadow:0 10px 40px -5px rgba(0,0,0,0.15); z-index:9999;">

                {{-- Header: date range inputs --}}
                <div class="flex items-center gap-0 px-4 py-3" style="border-bottom:1px solid #F3F4F6;">
                    {{-- Start date input --}}
                    <div class="flex-1 flex flex-col" style="border-bottom:2px solid #2563EB; padding-bottom:4px;">
                        <span class="text-sm"
                              :style="startStr ? 'color:#111827;' : 'color:#CBD5E1;'"
                              x-text="startStr ? startDisplay : 'DD/MM/YYYY'"></span>
                    </div>
                    {{-- Arrow --}}
                    <div class="flex-shrink-0 px-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#D1D5DB;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </div>
                    {{-- End date input --}}
                    <div class="flex-1 flex flex-col" style="border-bottom:2px solid #E5E7EB; padding-bottom:4px;">
                        <span class="text-sm"
                              :style="endStr ? 'color:#111827;' : 'color:#CBD5E1;'"
                              x-text="endStr ? endDisplay : 'DD/MM/YYYY'"></span>
                    </div>
                    {{-- Calendar icon + Clear --}}
                    <div class="flex items-center gap-2 pl-3 flex-shrink-0">
                        <button x-show="startStr" x-cloak type="button" @click="clearDates()"
                                class="text-xs font-medium cursor-pointer transition-colors"
                                style="color:#6B7280;"
                                onmouseover="this.style.color='#EF4444'"
                                onmouseout="this.style.color='#6B7280'">Clear</button>
                        <button type="button" @click="open = false"
                                class="w-7 h-7 flex items-center justify-center rounded cursor-pointer transition-colors"
                                style="color:#9CA3AF;"
                                onmouseover="this.style.background='#F3F4F6'"
                                onmouseout="this.style.background=''">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="flex">

                    {{-- Presets sidebar --}}
                    <div class="flex flex-col py-4 flex-shrink-0" style="width:152px; border-right:1px solid #F3F4F6;">
                        <template x-for="p in presets" :key="p.k">
                            <button type="button"
                                    @click="setPreset(p.k)"
                                    class="text-left text-sm transition-all cursor-pointer"
                                    :style="activePreset === p.k
                                        ? 'color:#111827; font-weight:600; background:#F9FAFB; border-left:3px solid #2563EB; padding:6px 12px 6px 9px;'
                                        : 'color:#6B7280; padding:6px 12px;'"
                                    onmouseover="if(this.getAttribute('data-active')!=='1'){this.style.background='#F9FAFB';this.style.color='#374151';}"
                                    onmouseout="if(this.getAttribute('data-active')!=='1'){this.style.background='';this.style.color='#6B7280';}"
                                    :data-active="activePreset === p.k ? '1' : '0'"
                                    x-text="p.l"></button>
                        </template>
                    </div>

                    {{-- Dual calendars --}}
                    <div class="flex-1 p-4" @mouseleave="hoverStr = ''">
                        <div class="grid grid-cols-2 gap-5">

                            {{-- Left calendar --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex gap-0">
                                        <button type="button" @click="prevYear()" class="cal-nav-btn" title="Prev year">«</button>
                                        <button type="button" @click="prevMonth()" class="cal-nav-btn" title="Prev month">‹</button>
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
                                            <button type="button"
                                                    :disabled="!day.c"
                                                    @click="day.c && selectDate(day.s)"
                                                    @mouseover="day.c && picking && (hoverStr = day.s)"
                                                    class="cal-btn"
                                                    :class="btnClass(day)"
                                                    x-text="day.d"></button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Right calendar --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div style="width:46px;"></div>
                                    <span class="text-sm font-bold select-none" style="color:#374151;" x-text="rightLabel"></span>
                                    <div class="flex gap-0">
                                        <button type="button" @click="nextMonth()" class="cal-nav-btn" title="Next month">›</button>
                                        <button type="button" @click="nextYear()" class="cal-nav-btn" title="Next year">»</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-7 mb-1">
                                    <div class="cal-dh">Su</div><div class="cal-dh">Mo</div><div class="cal-dh">Tu</div>
                                    <div class="cal-dh">We</div><div class="cal-dh">Th</div><div class="cal-dh">Fr</div><div class="cal-dh">Sa</div>
                                </div>
                                <div class="grid grid-cols-7">
                                    <template x-for="day in getDays(rightYear, rightMonth)" :key="'R'+day.s">
                                        <div class="cal-cell" :class="cellClass(day)">
                                            <button type="button"
                                                    :disabled="!day.c"
                                                    @click="day.c && selectDate(day.s)"
                                                    @mouseover="day.c && picking && (hoverStr = day.s)"
                                                    class="cal-btn"
                                                    :class="btnClass(day)"
                                                    x-text="day.d"></button>
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
        @endif

        {{-- ── Product filter ──────────────────────────────────────────────── --}}
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
                 select(val) {
                     this.selected = val;
                     this.open = false;
                     this.search = '';
                     document.getElementById('product-hidden-input').value = val;
                 },
                 toggle() {
                     this.open = !this.open;
                     if (this.open) this.$nextTick(() => {
                         const r = this.$refs.trigger.getBoundingClientRect();
                         this.dropPos = 'top:' + (r.bottom + 8) + 'px;right:' + (window.innerWidth - r.right) + 'px;';
                         this.$refs.searchInput && this.$refs.searchInput.focus();
                     });
                 }
             }"
             @keydown.escape.window="open = false">

            <button type="button" @click.stop="toggle()"
                    x-ref="trigger"
                    class="filter-icon-btn relative"
                    :class="selected ? 'filter-icon-btn--active' : ''"
                    aria-label="Filter by product" title="Filter by product">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span x-show="selected" x-cloak
                      class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full"
                      style="background:#EF4444; border:1.5px solid #fff;"></span>
            </button>

            <template x-teleport="body">
            <div x-show="open" x-cloak @click.outside="open = false" @click.stop
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-y-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-y-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-y-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-y-95 -translate-y-1"
                 class="fixed w-72 rounded-2xl overflow-hidden origin-top-right"
                 :style="dropPos + 'z-index:9999;background:#0F172A;border:1px solid #1E293B;box-shadow:0 25px 50px -12px rgba(0,0,0,0.6),0 0 0 1px rgba(245,166,35,0.1);'">

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
                                <p class="text-xs" style="color:#475569;" x-text="selected ? 'Showing 1 product only' : '{{ count($products ?? []) }} products total'"></p>
                            </div>
                        </div>
                        <button x-show="selected" type="button" @click.stop="select('')"
                                class="flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-lg transition-all cursor-pointer"
                                style="color:#F87171; background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.2);"
                                onmouseover="this.style.background='rgba(248,113,113,0.2)'"
                                onmouseout="this.style.background='rgba(248,113,113,0.1)'">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            Clear
                        </button>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-2 rounded-xl"
                         style="background:#1E293B; border:1.5px solid #334155;"
                         x-bind:style="search.length > 0 ? 'border-color:#F5A623;box-shadow:0 0 0 3px rgba(245,166,35,0.12)' : ''">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             :style="search.length > 0 ? 'color:#F5A623' : 'color:#475569'">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" x-model="search" x-ref="searchInput"
                               placeholder="Search products…" autocomplete="off"
                               class="text-xs border-0 outline-none w-full"
                               style="background:#1E293B; color:#E2E8F0; caret-color:#F5A623;" @click.stop>
                        <button x-show="search" @click.stop="search=''" type="button"
                                class="flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center cursor-pointer"
                                style="background:#334155; color:#64748B;"
                                onmouseover="this.style.background='#475569';this.style.color='#E2E8F0'"
                                onmouseout="this.style.background='#334155';this.style.color='#64748B'">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>

                <div class="overflow-y-auto" style="max-height:220px; scrollbar-width:thin; scrollbar-color:#1E293B transparent;">
                    <button type="button" @click="select('')"
                            class="w-full text-left px-4 py-2.5 flex items-center gap-3 cursor-pointer transition-all"
                            :style="selected === '' ? 'background:rgba(245,166,35,0.1);' : ''"
                            onmouseover="if(this.getAttribute('data-s')==='0')this.style.background='rgba(255,255,255,0.03)'"
                            onmouseout="if(this.getAttribute('data-s')==='0')this.style.background=''"
                            :data-s="selected === '' ? '1' : '0'">
                        <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0"
                             :style="selected === '' ? 'background:#F5A623;box-shadow:0 0 8px rgba(245,166,35,0.4)' : 'background:#1E293B;border:1.5px solid #334155'">
                            <svg x-show="selected === ''" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" style="color:#0F172A;"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                        <span class="text-xs font-semibold" :style="selected === '' ? 'color:#F5A623' : 'color:#94A3B8'">All Products</span>
                        <span class="ml-auto text-xs rounded-md px-1.5 py-0.5 font-mono" style="background:#1E293B; color:#475569; font-size:10px;">{{ count($products ?? []) }}</span>
                    </button>
                    <div style="height:1px; background:#1E293B; margin:0 16px;"></div>
                    <template x-for="product in filteredProducts" :key="product">
                        <button type="button" @click="select(product)"
                                class="w-full text-left px-4 py-2.5 flex items-center gap-3 cursor-pointer transition-all"
                                :style="selected === product ? 'background:rgba(245,166,35,0.1);' : ''"
                                onmouseover="if(this.getAttribute('data-s')==='0')this.style.background='rgba(255,255,255,0.03)'"
                                onmouseout="if(this.getAttribute('data-s')==='0')this.style.background=''"
                                :data-s="selected === product ? '1' : '0'">
                            <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0"
                                 :style="selected === product ? 'background:#F5A623;box-shadow:0 0 8px rgba(245,166,35,0.4)' : 'background:#1E293B;border:1.5px solid #334155'">
                                <svg x-show="selected === product" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" style="color:#0F172A;"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                            <span class="text-xs truncate" :style="selected === product ? 'color:#F5A623;font-weight:600' : 'color:#94A3B8'" x-text="product"></span>
                        </button>
                    </template>
                    <div x-show="filteredProducts.length === 0 && search.length > 0" class="px-4 py-6 text-center">
                        <p class="text-xs font-medium" style="color:#475569;">No match for</p>
                        <p class="text-xs font-bold mt-0.5" style="color:#64748B;" x-text='"«" + search + "»"'></p>
                        <button type="button" @click.stop="search=''" class="mt-2 text-xs font-medium cursor-pointer" style="color:#F5A623;">Clear</button>
                    </div>
                </div>

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
        </div>

        {{-- Extra filters slot --}}
        @isset($extraFilters)
        <div class="w-px h-5 mx-0.5 flex-shrink-0" style="background:#E2E8F0;"></div>
        {!! $extraFilters !!}
        @endisset

        <div class="w-px h-5 mx-0.5 flex-shrink-0" style="background:#E2E8F0;"></div>

        {{-- Apply --}}
        <button type="submit"
                class="filter-icon-btn filter-icon-btn--apply"
                aria-label="Apply filters" title="Apply filters">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
        </button>

    </div>
</form>

<script>
function calPicker(fromVal, toVal) {
    const MN = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const DN = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
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
            const startPad = first.getDay(); // 0=Sun
            const days     = [];
            // padding before
            for (let i = startPad - 1; i >= 0; i--) {
                const d = new Date(yr, mo, -i); // -0 === 0 → day 0 = last day of prev month
                days.push({s: this.ds(d), d: d.getDate(), c: false});
            }
            // current month
            for (let i = 1; i <= total; i++) {
                days.push({s: this.ds(new Date(yr, mo, i)), d: i, c: true});
            }
            // padding after (fill to 42 cells = 6 rows)
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
            if (start && end) return ''; // single-day: just the circle
            const d = this.dow(s);
            let cls = 'in-range';
            if (start || d === 0) cls += ' row-cap-l'; // pill left cap
            if (end   || d === 6) cls += ' row-cap-r'; // pill right cap
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
                this.startStr    = s;
                this.endStr      = '';
                this.picking     = true;
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
