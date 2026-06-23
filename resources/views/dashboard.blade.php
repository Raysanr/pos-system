@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Overview of your Pancake POS performance')

@section('header_actions')
    @include('partials.filter-bar', [
        'filterRoute'   => route('dashboard'),
        'products'      => $products,
        'productFilter' => $productFilter,
        'datePreset'    => $datePreset,
        'dateFrom'      => $dateFrom,
        'dateTo'        => $dateTo,
    ])
@endsection

@section('content')
<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Confirmed Revenue</span>
            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div id="kpi-revenue" class="text-2xl font-bold text-slate-900 font-mono">₱{{ number_format($kpis['total_revenue']) }}</div>
        <div id="kpi-revenue-change" class="mt-1 flex items-center gap-1 text-xs {{ $kpis['revenue_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
            @if($kpis['revenue_change'] >= 0)
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            @else
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            @endif
            {{ abs($kpis['revenue_change']) }}% vs prev period
        </div>
    </div>

    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Orders</span>
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
        </div>
        <div id="kpi-orders" class="text-2xl font-bold text-slate-900 font-mono">{{ number_format($kpis['total_orders']) }}</div>
        <div id="kpi-orders-change" class="mt-1 flex items-center gap-1 text-xs {{ $kpis['orders_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
            @if($kpis['orders_change'] >= 0)
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            @else
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            @endif
            {{ abs($kpis['orders_change']) }}% vs prev period
        </div>
    </div>

    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">RTS Rate</span>
            <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
            </div>
        </div>
        <div id="kpi-rts-rate" class="text-2xl font-bold text-slate-900 font-mono">{{ $kpis['rts_rate'] }}%</div>
        <div id="kpi-rts-sub" class="mt-1 text-xs text-slate-500">{{ number_format($kpis['rts_count']) }} orders returned</div>
    </div>

    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Avg Order Value</span>
            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
        </div>
        <div id="kpi-avg-order" class="text-2xl font-bold text-slate-900 font-mono">₱{{ number_format($kpis['avg_order_value']) }}</div>
        <div id="kpi-new-customers" class="mt-1 text-xs text-slate-500">{{ number_format($kpis['new_customers']) }} new customers</div>
    </div>

    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Bottles Sold</span>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:#F0FDF4;">
                <svg class="w-4 h-4" style="color:#10B981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            </div>
        </div>
        <div id="kpi-bottles" class="text-2xl font-bold font-mono" style="color:#10B981;">{{ number_format($kpis['total_bottles_sold']) }}</div>
        <div class="mt-1 text-xs text-slate-500">delivered bottles</div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="lg:col-span-2 bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Confirmed Revenue & Orders Trend</h3>
                <p class="text-xs text-slate-400">Delivered orders only — daily breakdown for selected period</p>
            </div>
        </div>
        <canvas id="revenueChart" height="100"></canvas>
    </div>
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-slate-800">Order Status</h3>
            <p class="text-xs text-slate-400">Distribution by status</p>
        </div>
        <canvas id="statusChart" height="180"></canvas>
    </div>
</div>

<!-- Tables Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Top Provinces</h3>
                <p class="text-xs text-slate-400">By order volume</p>
            </div>
            <a href="{{ route('analytics.map') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">View Map</a>
        </div>
        <table class="w-full">
            <thead><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">Province</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">Orders</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">Revenue</th>
            </tr></thead>
            <tbody id="tbody-provinces">
                @forelse($topProvinces as $prov)
                <tr class="border-t border-slate-100 hover:bg-blue-50/40 transition-colors">
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $prov['province'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">{{ number_format($prov['orders']) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">₱{{ number_format($prov['revenue']) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No data yet. Sync your orders first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Courier Performance</h3>
                <p class="text-xs text-slate-400">Orders vs RTS by courier</p>
            </div>
            <a href="{{ route('analytics.rts') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">View RTS</a>
        </div>
        <table class="w-full">
            <thead><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">Courier</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">Orders</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">RTS</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">Rate</th>
            </tr></thead>
            <tbody id="tbody-couriers">
                @forelse($topCouriers as $c)
                @php $rtsRate = $c['orders'] > 0 ? round(($c['rts']/$c['orders'])*100,1) : 0; @endphp
                <tr class="border-t border-slate-100 hover:bg-blue-50/40 transition-colors">
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $c['courier'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">{{ number_format($c['orders']) }}</td>
                    <td class="px-4 py-3 text-sm text-red-600 text-right font-mono">{{ number_format($c['rts']) }}</td>
                    <td class="px-4 py-3 text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $rtsRate > 20 ? 'bg-red-100 text-red-700' : ($rtsRate > 10 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }}">{{ $rtsRate }}%</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No data yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script id="revenue-data" type="application/json"><?php echo json_encode($revenueChart); ?></script>
<script id="status-data" type="application/json"><?php echo json_encode($orderStatusChart); ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const revenueData = JSON.parse(document.getElementById('revenue-data').textContent);
const statusData = JSON.parse(document.getElementById('status-data').textContent);

window.__charts = {};

// Revenue chart
window.__charts.revenue = new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: revenueData.labels,
        datasets: [
            {
                label: 'Revenue (₱)',
                data: revenueData.revenue,
                backgroundColor: 'rgba(30,64,175,0.15)',
                borderColor: '#1E40AF',
                borderWidth: 2,
                borderRadius: 4,
                yAxisID: 'y',
            },
            {
                label: 'Orders',
                data: revenueData.orders,
                type: 'line',
                borderColor: '#D97706',
                backgroundColor: 'rgba(217,119,6,0.1)',
                borderWidth: 2,
                pointRadius: 3,
                tension: 0.4,
                fill: false,
                yAxisID: 'y1',
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { font: { size: 11, family: 'Fira Sans' }, boxWidth: 12 } }, tooltip: { callbacks: { label: ctx => ctx.datasetIndex===0 ? `Revenue: ₱${ctx.raw.toLocaleString()}` : `Orders: ${ctx.raw}` } } },
        scales: {
            x: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, maxRotation: 45 } },
            y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, callback: v => '₱'+v.toLocaleString() } },
            y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 10 } } }
        }
    }
});

// Status donut
const statusColors = ['#1E40AF','#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#6B7280'];
window.__charts.status = new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusData.labels,
        datasets: [{ data: statusData.data, backgroundColor: statusColors.slice(0, statusData.labels.length), borderWidth: 2, borderColor: '#fff', hoverOffset: 4 }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11, family: 'Fira Sans' }, boxWidth: 10, padding: 8 } },
            tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw.toLocaleString()}` } }
        },
        cutout: '65%'
    }
});

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function changeHtml(pct) {
    const up = pct >= 0;
    const arrow = up
        ? '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>'
        : '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
    return { html: arrow + Math.abs(pct) + '% vs prev period', cls: up ? 'text-green-600' : 'text-red-600' };
}

window.__ajaxFilterUpdate = async function (form, params, url) {
    const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const d = await res.json();

    // KPIs
    document.getElementById('kpi-revenue').textContent = '₱' + Number(d.kpis.total_revenue).toLocaleString();
    const revCh = changeHtml(d.kpis.revenue_change);
    const revEl = document.getElementById('kpi-revenue-change');
    revEl.innerHTML = revCh.html;
    revEl.className = 'mt-1 flex items-center gap-1 text-xs ' + revCh.cls;

    document.getElementById('kpi-orders').textContent = Number(d.kpis.total_orders).toLocaleString();
    const ordCh = changeHtml(d.kpis.orders_change);
    const ordEl = document.getElementById('kpi-orders-change');
    ordEl.innerHTML = ordCh.html;
    ordEl.className = 'mt-1 flex items-center gap-1 text-xs ' + ordCh.cls;

    document.getElementById('kpi-rts-rate').textContent = d.kpis.rts_rate + '%';
    document.getElementById('kpi-rts-sub').textContent  = Number(d.kpis.rts_count).toLocaleString() + ' orders returned';
    document.getElementById('kpi-avg-order').textContent    = '₱' + Number(d.kpis.avg_order_value).toLocaleString();
    document.getElementById('kpi-new-customers').textContent = Number(d.kpis.new_customers).toLocaleString() + ' new customers';
    document.getElementById('kpi-bottles').textContent = Number(d.kpis.total_bottles_sold).toLocaleString();

    // Revenue chart
    window.__charts.revenue.data.labels = d.revenueChart.labels;
    window.__charts.revenue.data.datasets[0].data = d.revenueChart.revenue;
    window.__charts.revenue.data.datasets[1].data = d.revenueChart.orders;
    window.__charts.revenue.update();

    // Status chart
    window.__charts.status.data.labels = d.orderStatusChart.labels;
    window.__charts.status.data.datasets[0].data = d.orderStatusChart.data;
    window.__charts.status.update();

    // Provinces table
    document.getElementById('tbody-provinces').innerHTML = d.topProvinces.length
        ? d.topProvinces.map(p => `<tr class="border-t border-slate-100 hover:bg-blue-50/40 transition-colors">
            <td class="px-4 py-3 text-sm text-slate-700">${escHtml(p.province)}</td>
            <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">${Number(p.orders).toLocaleString()}</td>
            <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">₱${Number(p.revenue).toLocaleString()}</td>
        </tr>`).join('')
        : `<tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No data yet.</td></tr>`;

    // Couriers table
    document.getElementById('tbody-couriers').innerHTML = d.topCouriers.length
        ? d.topCouriers.map(c => {
            const rate = c.orders > 0 ? Math.round(c.rts / c.orders * 1000) / 10 : 0;
            const cls  = rate > 20 ? 'bg-red-100 text-red-700' : rate > 10 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700';
            return `<tr class="border-t border-slate-100 hover:bg-blue-50/40 transition-colors">
                <td class="px-4 py-3 text-sm text-slate-700">${escHtml(c.courier)}</td>
                <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">${Number(c.orders).toLocaleString()}</td>
                <td class="px-4 py-3 text-sm text-red-600 text-right font-mono">${Number(c.rts).toLocaleString()}</td>
                <td class="px-4 py-3 text-right"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${rate}%</span></td>
            </tr>`;
        }).join('')
        : `<tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No data yet.</td></tr>`;
};
</script>
@endpush
