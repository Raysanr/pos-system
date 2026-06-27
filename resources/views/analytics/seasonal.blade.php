@extends('layouts.app')
@section('title', 'Seasonal Trends')
@section('subtitle', 'Order patterns by day, week, and month — with Philippine calendar context')

@section('header_actions')
    @include('partials.filter-bar', [
        'filterRoute'    => route('analytics.seasonal'),
        'products'       => $products,
        'productFilter'  => $productFilter,
        'showDateFilter' => false,
    ])
@endsection

@section('content')
@php
    $daily      = $data['daily'];
    $weekly     = $data['weekly'];
    $dayOfMonth = $data['dayOfMonth'];
    $dayOfWeek  = $data['dayOfWeek'];
    $monthly    = $data['monthly'];

    $phHolidays = [
        '2026-01-01' => "New Year's Day",
        '2026-02-25' => 'EDSA Revolution',
        '2026-04-02' => 'Maundy Thursday',
        '2026-04-03' => 'Good Friday',
        '2026-04-04' => 'Black Saturday',
        '2026-04-09' => 'Araw ng Kagitingan',
        '2026-05-01' => 'Labor Day',
        '2026-06-12' => 'Independence Day',
        '2026-08-21' => 'Ninoy Aquino Day',
        '2026-08-31' => 'National Heroes Day',
        '2026-11-01' => 'All Saints Day',
        '2026-11-30' => 'Bonifacio Day',
        '2026-12-25' => 'Christmas Day',
        '2026-12-30' => 'Rizal Day',
    ];

    $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    // Find max daily orders for heatmap intensity
    $maxDaily = max(array_map(fn($d) => $d['orders'], $daily) ?: [1]);
@endphp

{{-- ── Monthly Summary Cards ──────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
@foreach($monthly as $m)
@php
    $parts    = explode('-', $m['month']);
    $label    = $monthNames[(int)$parts[1] - 1] . ' ' . $parts[0];
    $delRate  = $m['orders'] > 0 ? round($m['delivered'] / $m['orders'] * 100, 1) : 0;
    $rtsRate  = $m['orders'] > 0 ? round($m['rts'] / $m['orders'] * 100, 1) : 0;
    $rev      = $m['revenue'] >= 1e6 ? '₱'.number_format($m['revenue']/1e6,1).'M' : '₱'.number_format($m['revenue']/1e3,0).'K';
@endphp
<div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">{{ $label }}</div>
    <div class="text-lg font-bold text-slate-800">{{ number_format($m['orders']) }}</div>
    <div class="text-xs text-slate-400 mb-2">orders</div>
    <div class="text-sm font-semibold" style="color:#059669;">{{ $rev }}</div>
    <div class="flex items-center gap-2 mt-2">
        <span class="text-xs px-1.5 py-0.5 rounded font-medium {{ $delRate >= 75 ? 'bg-green-100 text-green-700' : ($delRate >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $delRate }}% del</span>
        <span class="text-xs px-1.5 py-0.5 rounded font-medium {{ $rtsRate <= 15 ? 'bg-green-100 text-green-700' : ($rtsRate <= 25 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $rtsRate }}% rts</span>
    </div>
</div>
@endforeach
</div>

{{-- ── Activity Heatmap Calendar ────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 mb-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h3 class="text-sm font-semibold text-slate-800">Daily Order Activity</h3>
            <p class="text-xs text-slate-400 mt-0.5">Last 52 weeks · Each cell = 1 day · Hover for details</p>
        </div>
        <div class="flex items-center gap-2 text-xs text-slate-400">
            <span>Low</span>
            <div class="flex gap-1">
                @foreach(['#e2e8f0','#bfdbfe','#93c5fd','#3b82f6','#1d4ed8'] as $c)
                <div class="w-3.5 h-3.5 rounded" style="background:{{ $c }}"></div>
                @endforeach
            </div>
            <span>High</span>
        </div>
    </div>

    <div class="overflow-x-auto pb-1">
        <div id="heatmap-grid" style="display:flex;gap:3px;min-width:max-content;"></div>
    </div>

    {{-- Tooltip --}}
    <div id="heatmap-tooltip"
         class="fixed z-[9999] hidden pointer-events-none"
         style="min-width:180px;">
        <div class="bg-slate-900 text-white text-xs rounded-xl px-3.5 py-2.5 shadow-2xl" style="border:1px solid #334155;">
            <div id="heatmap-tt-content"></div>
        </div>
    </div>
</div>

{{-- ── Weekly Trend ─────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 mb-5">
    <div class="mb-4">
        <h3 class="text-sm font-semibold text-slate-800">Weekly Order Trend</h3>
        <p class="text-xs text-slate-400 mt-0.5">Orders and delivered revenue per week · PH holidays marked</p>
    </div>
    <div class="chart-wrap" style="position:relative;height:260px;">
        <canvas id="weeklyChart" class="chart-canvas"></canvas>
    </div>
</div>

{{-- ── Payday Pattern ───────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 mb-5">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800">Payday Pattern — Orders by Day of Month</h3>
            <p class="text-xs text-slate-400 mt-0.5">Cumulative orders per calendar day across all months · Paydays (15th & 30th) marked</p>
        </div>
        <div id="payday-insight" class="text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-3 py-2 max-w-xs text-right"></div>
    </div>
    <div class="chart-wrap" style="position:relative;height:220px;">
        <canvas id="domChart" class="chart-canvas"></canvas>
    </div>
</div>

{{-- ── Insights Panel ───────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-slate-800 mb-4">Key Insights from Your Data</h3>
    <div id="insights-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"></div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const DAILY      = @json($daily);
const WEEKLY     = @json($weekly);
const DAY_MONTH  = @json($dayOfMonth);
const DAY_WEEK   = @json($dayOfWeek);
const MONTHLY    = @json($monthly);
const HOLIDAYS   = @json($phHolidays);
const MAX_DAILY  = {{ $maxDaily }};

// ── Colour helpers ────────────────────────────────────────────────────────────
function heatColor(orders) {
    if (orders === 0) return '#f1f5f9';
    const pct = Math.min(orders / MAX_DAILY, 1);
    if (pct < 0.25) return '#bfdbfe';
    if (pct < 0.50) return '#93c5fd';
    if (pct < 0.75) return '#3b82f6';
    return '#1d4ed8';
}

function fmt(v) {
    if (v >= 1e6) return '₱' + (v / 1e6).toFixed(1) + 'M';
    if (v >= 1e3) return '₱' + (v / 1e3).toFixed(0) + 'K';
    return '₱' + v.toLocaleString();
}

// ── Heatmap Calendar ─────────────────────────────────────────────────────────
try { (function buildHeatmap() {
    const grid    = document.getElementById('heatmap-grid');
    const ttWrap  = document.getElementById('heatmap-tooltip');
    const ttBody  = document.getElementById('heatmap-tt-content');
    if (!grid) return;

    const CELL = 14; // px
    const GAP  = 3;  // px
    const STEP = CELL + GAP;

    const dayLabels  = ['Mon','','Wed','','Fri','','Sun'];
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    // Always show exactly last 52 weeks (Sun → Sat or Mon → Sun)
    const today = new Date();
    today.setHours(0,0,0,0);

    // Walk back to the Monday 52 weeks ago
    const start = new Date(today);
    const dow   = (start.getDay() + 6) % 7; // 0=Mon
    start.setDate(start.getDate() - dow - 51 * 7);

    const localStr = d => {
        return d.getFullYear() + '-'
             + String(d.getMonth() + 1).padStart(2,'0') + '-'
             + String(d.getDate()).padStart(2,'0');
    };

    // Build weeks array
    const weeks = [];
    const cur = new Date(start);
    while (cur <= today) {
        const week = [];
        for (let i = 0; i < 7; i++) {
            if (cur > today) { week.push(null); }
            else             { week.push(new Date(cur)); }
            cur.setDate(cur.getDate() + 1);
        }
        weeks.push(week);
    }

    // Month label row: one label per week column if month changed
    const monthRow = document.createElement('div');
    monthRow.style.cssText = `display:flex;gap:${GAP}px;margin-bottom:4px;padding-left:${STEP + 6}px;`;
    let prevMo = -1;
    weeks.forEach(week => {
        const firstDay = week.find(d => d !== null);
        const span = document.createElement('div');
        span.style.cssText = `width:${CELL}px;flex-shrink:0;font-size:10px;color:#94a3b8;font-weight:500;white-space:nowrap;`;
        if (firstDay && firstDay.getMonth() !== prevMo) {
            span.textContent = monthNames[firstDay.getMonth()];
            prevMo = firstDay.getMonth();
        }
        monthRow.appendChild(span);
    });
    grid.parentElement.insertBefore(monthRow, grid);

    // Day-of-week label column
    const labelCol = document.createElement('div');
    labelCol.style.cssText = `display:flex;flex-direction:column;gap:${GAP}px;margin-right:6px;flex-shrink:0;`;
    dayLabels.forEach(l => {
        const div = document.createElement('div');
        div.style.cssText = `height:${CELL}px;font-size:9px;color:#94a3b8;display:flex;align-items:center;font-weight:500;`;
        div.textContent = l;
        labelCol.appendChild(div);
    });
    grid.appendChild(labelCol);

    // Week columns
    weeks.forEach(week => {
        const col = document.createElement('div');
        col.style.cssText = `display:flex;flex-direction:column;gap:${GAP}px;flex-shrink:0;`;

        week.forEach((day, rowIdx) => {
            const cell = document.createElement('div');
            cell.style.cssText = `width:${CELL}px;height:${CELL}px;border-radius:3px;cursor:pointer;transition:transform 0.1s,outline 0.1s;flex-shrink:0;`;

            if (!day) {
                cell.style.background = 'transparent';
            } else {
                const ds        = localStr(day);
                const d         = DAILY[ds] || { orders: 0, delivered: 0, revenue: 0 };
                const isHoliday = HOLIDAYS[ds];
                cell.style.background = heatColor(d.orders);
                if (isHoliday) {
                    cell.style.outline = '2px solid #f59e0b';
                    cell.style.outlineOffset = '-1px';
                }

                cell.addEventListener('mouseenter', e => {
                    cell.style.transform = 'scale(1.3)';
                    cell.style.zIndex    = '10';
                    if (ttWrap && ttBody) {
                        const delRate = d.orders > 0 ? Math.round(d.delivered / d.orders * 100) : 0;
                        const dow2    = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][day.getDay()];
                        ttBody.innerHTML =
                            `<div style="font-weight:600;margin-bottom:6px;color:#e2e8f0;">${dow2}, ${ds}${isHoliday ? '<br><span style="color:#fbbf24;font-weight:400;">📅 ' + isHoliday + '</span>' : ''}</div>` +
                            `<div style="display:flex;justify-content:space-between;gap:16px;"><span style="color:#94a3b8;">Orders</span><span style="font-weight:600;">${d.orders.toLocaleString()}</span></div>` +
                            `<div style="display:flex;justify-content:space-between;gap:16px;"><span style="color:#94a3b8;">Delivered</span><span style="color:#86efac;font-weight:600;">${d.delivered.toLocaleString()} (${delRate}%)</span></div>` +
                            `<div style="display:flex;justify-content:space-between;gap:16px;"><span style="color:#94a3b8;">Revenue</span><span style="color:#6ee7b7;font-weight:600;">${fmt(d.revenue)}</span></div>`;
                        ttWrap.classList.remove('hidden');
                    }
                });
                cell.addEventListener('mousemove', e => {
                    if (ttWrap) {
                        ttWrap.style.left = (e.clientX + 16) + 'px';
                        ttWrap.style.top  = (e.clientY - 10) + 'px';
                    }
                });
                cell.addEventListener('mouseleave', () => {
                    cell.style.transform = '';
                    cell.style.zIndex    = '';
                    if (ttWrap) ttWrap.classList.add('hidden');
                });
            }
            col.appendChild(cell);
        });
        grid.appendChild(col);
    });
})(); } catch(e) { console.error('buildHeatmap:', e); }

// ── Weekly Trend Chart ────────────────────────────────────────────────────────
try { (function buildWeekly() {
    const ctx = document.getElementById('weeklyChart');
    if (!ctx || !WEEKLY.length) return;

    const labels   = WEEKLY.map(w => w.week_start);
    const orders   = WEEKLY.map(w => w.orders);
    const revenue  = WEEKLY.map(w => w.revenue);

    // Find weeks containing PH holidays
    const holidayDates = Object.keys(HOLIDAYS);
    const annotations  = {};
    WEEKLY.forEach((w, i) => {
        const wStart = new Date(w.week_start + 'T00:00:00');
        const wEnd   = new Date(wStart); wEnd.setDate(wEnd.getDate() + 6);
        holidayDates.forEach(h => {
            const hd = new Date(h + 'T00:00:00');
            if (hd >= wStart && hd <= wEnd) {
                annotations['holiday_' + i] = {
                    type: 'line', xMin: i, xMax: i,
                    borderColor: 'rgba(245,158,11,0.6)', borderWidth: 2, borderDash: [4,3],
                    label: { content: HOLIDAYS[h], display: true, position: 'start', color: '#92400e', font: { size: 9 }, backgroundColor: 'rgba(254,243,199,0.9)', padding: 2 }
                };
            }
        });
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Orders',
                    data: orders,
                    backgroundColor: 'rgba(59,130,246,0.25)',
                    borderColor: '#3b82f6',
                    borderWidth: 1.5,
                    borderRadius: 3,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    label: 'Revenue',
                    data: revenue,
                    type: 'line',
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    borderWidth: 2,
                    pointRadius: 2,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y2',
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label === 'Revenue'
                            ? 'Revenue: ' + fmt(ctx.raw)
                            : 'Orders: ' + ctx.raw.toLocaleString()
                    }
                }
            },
            scales: {
                x: { ticks: { font: { size: 10 }, maxTicksLimit: 12, maxRotation: 0 }, grid: { display: false } },
                y:  { position: 'left',  ticks: { font: { size: 10 } }, title: { display: true, text: 'Orders', font: { size: 10 } } },
                y2: { position: 'right', ticks: { font: { size: 10 }, callback: v => fmt(v) }, grid: { drawOnChartArea: false }, title: { display: true, text: 'Revenue', font: { size: 10 } } }
            }
        }
    });
    ctx.classList.add('loaded');
})(); } catch(e) { console.error('buildWeekly:', e); }

// ── Day of Week Chart ─────────────────────────────────────────────────────────
try { (function buildDow() {
    const ctx = document.getElementById('dowChart');
    if (!ctx) return;

    const labels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const orders = Object.values(DAY_WEEK).map(d => d.orders);
    const maxOrd = Math.max(...orders);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Total Orders',
                data: orders,
                backgroundColor: orders.map(v => v === maxOrd ? '#f59e0b' : 'rgba(59,130,246,0.35)'),
                borderColor:     orders.map(v => v === maxOrd ? '#d97706' : '#3b82f6'),
                borderWidth: 1.5,
                borderRadius: 5,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ctx.raw.toLocaleString() + ' orders' } }
            },
            scales: {
                x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                y: { ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' } }
            }
        }
    });
    ctx.classList.add('loaded');
})(); } catch(e) { console.error('buildDow:', e); }

// ── Day of Month (Payday Pattern) Chart ──────────────────────────────────────
try { (function buildDom() {
    const ctx = document.getElementById('domChart');
    if (!ctx) return;

    const labels = Array.from({length: 31}, (_, i) => i + 1);
    const orders = labels.map(d => DAY_MONTH[d]?.orders ?? 0);
    const maxOrd = Math.max(...orders);

    // Detect actual spike window (top 20% of days)
    const threshold = maxOrd * 0.85;
    const spikeDays = labels.filter(d => (DAY_MONTH[d]?.orders ?? 0) >= threshold);

    // Build payday insight text
    const insightEl = document.getElementById('payday-insight');
    if (insightEl && spikeDays.length) {
        const first = spikeDays[0], last = spikeDays[spikeDays.length - 1];
        insightEl.innerHTML = `📈 <strong>Peak window: Day ${first}–${last}</strong><br>Your real sales spike isn't on the 15th & 30th — it's days ${first}–${last}. Load more ad budget then.`;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Orders',
                data: orders,
                backgroundColor: labels.map(d => {
                    if (d === 15 || d === 30) return 'rgba(245,158,11,0.6)';
                    if ((DAY_MONTH[d]?.orders ?? 0) >= threshold) return '#3b82f6';
                    return 'rgba(148,163,184,0.35)';
                }),
                borderColor: labels.map(d => {
                    if (d === 15 || d === 30) return '#d97706';
                    if ((DAY_MONTH[d]?.orders ?? 0) >= threshold) return '#1d4ed8';
                    return '#94a3b8';
                }),
                borderWidth: 1.5,
                borderRadius: 3,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: ctx => 'Day ' + ctx[0].label + ' of month',
                        label: ctx => {
                            const d = DAY_MONTH[ctx.label];
                            if (!d) return ctx.raw.toLocaleString() + ' orders';
                            const rate = d.orders > 0 ? Math.round(d.delivered / d.orders * 100) : 0;
                            return [ctx.raw.toLocaleString() + ' orders', rate + '% delivery rate', fmt(d.revenue) + ' revenue'];
                        },
                        afterTitle: ctx => {
                            const day = parseInt(ctx[0].label);
                            if (day === 15) return '🟡 Traditional payday';
                            if (day === 30) return '🟡 Traditional payday';
                            return '';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        font: { size: 10 },
                        callback: (val, i) => {
                            const d = i + 1;
                            if (d === 15 || d === 30) return '▲' + d;
                            return d;
                        }
                    },
                    grid: { display: false }
                },
                y: { ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' } }
            }
        }
    });
    ctx.classList.add('loaded');
})(); } catch(e) { console.error('buildDom:', e); }

// ── Insights Generator ────────────────────────────────────────────────────────
try { (function buildInsights() {
    const el = document.getElementById('insights-grid');
    if (!el || !MONTHLY.length || !WEEKLY.length) return;

    const insights = [];

    // Best and worst month
    const byRevenue = [...MONTHLY].sort((a,b) => b.revenue - a.revenue);
    if (byRevenue.length >= 2) {
        const best  = byRevenue[0];
        const worst = byRevenue[byRevenue.length - 1];
        const mNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const bLabel = mNames[parseInt(best.month.split('-')[1]) - 1];
        const wLabel = mNames[parseInt(worst.month.split('-')[1]) - 1];
        insights.push({ icon:'🏆', color:'green', title:'Best Month', body:`<strong>${bLabel}</strong> was your top month — ₱${(best.revenue/1e6).toFixed(1)}M revenue from ${best.orders.toLocaleString()} orders.` });
        insights.push({ icon:'📉', color:'red', title:'Weakest Month', body:`<strong>${wLabel}</strong> had the lowest revenue. Study what was different to avoid a repeat.` });
    }

    // Best day of week
    const dowNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const dowArr   = Object.entries(DAY_WEEK).map(([dow, d]) => ({dow: parseInt(dow), ...d}));
    const bestDow  = dowArr.sort((a,b) => b.orders - a.orders)[0];
    const worstDow = dowArr[dowArr.length - 1];
    if (bestDow) {
        insights.push({ icon:'📅', color:'blue', title:'Best Day to Push', body:`<strong>${dowNames[bestDow.dow]}</strong> gets the most orders on average. Prioritise ad spend and CS staffing on this day.` });
    }
    if (worstDow) {
        insights.push({ icon:'😴', color:'slate', title:'Slowest Day', body:`<strong>${dowNames[worstDow.dow]}</strong> is your slowest. Consider rest day schedules or flash promos to lift volume.` });
    }

    // Payday insight — real spike
    const domArr   = Object.entries(DAY_MONTH).map(([d, v]) => ({day: parseInt(d), ...v}));
    const maxDomOrd = Math.max(...domArr.map(d => d.orders));
    const spikeDays = domArr.filter(d => d.orders >= maxDomOrd * 0.85).map(d => d.day);
    if (spikeDays.length) {
        const f = spikeDays[0], l = spikeDays[spikeDays.length - 1];
        insights.push({ icon:'💸', color:'amber', title:'Real Payday Spike', body:`Your peak isn't the 15th & 30th — it's <strong>days ${f}–${l}</strong>. This is when customers actually have budget. Time your ads accordingly.` });
    }

    // Delivery rate trend (last 4 weeks)
    const last4 = WEEKLY.slice(-4);
    if (last4.length === 4) {
        const early = last4.slice(0,2).reduce((s,w) => s + (w.orders > 0 ? w.delivered/w.orders : 0), 0) / 2;
        const late  = last4.slice(2,4).reduce((s,w) => s + (w.orders > 0 ? w.delivered/w.orders : 0), 0) / 2;
        const diff  = Math.round((late - early) * 100);
        if (Math.abs(diff) >= 3) {
            insights.push({
                icon: diff > 0 ? '📈' : '⚠️',
                color: diff > 0 ? 'green' : 'red',
                title: diff > 0 ? 'Delivery Rate Improving' : 'Delivery Rate Dropping',
                body: `Your delivery rate is <strong>${diff > 0 ? '+' : ''}${diff}%</strong> over the last 2 weeks vs. the 2 weeks before. ${diff < 0 ? 'Investigate courier or provincial issues now.' : "Keep up what's working."}`
            });
        }
    }

    const colorMap = {
        green: 'bg-green-50 border-green-200 text-green-800',
        red:   'bg-red-50 border-red-200 text-red-800',
        blue:  'bg-blue-50 border-blue-200 text-blue-800',
        amber: 'bg-amber-50 border-amber-200 text-amber-800',
        slate: 'bg-slate-50 border-slate-200 text-slate-700',
    };

    el.innerHTML = insights.map(ins => `
        <div class="rounded-xl border p-4 ${colorMap[ins.color] ?? colorMap.slate}">
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-lg">${ins.icon}</span>
                <span class="text-xs font-bold uppercase tracking-wider">${ins.title}</span>
            </div>
            <p class="text-xs leading-relaxed">${ins.body}</p>
        </div>
    `).join('');
})(); } catch(e) { console.error('buildInsights:', e); }
</script>
@endpush
