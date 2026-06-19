@extends('layouts.app')
@section('title', 'Customer Analytics')
@section('subtitle', 'Demographics, geography, and behavior analysis')

@section('header_actions')
    @include('partials.filter-bar', [
        'filterRoute'   => route('analytics.customers'),
        'products'      => $products,
        'productFilter' => $productFilter,
        'datePreset'    => $datePreset,
        'dateFrom'      => $dateFrom,
        'dateTo'        => $dateTo,
    ])
@endsection

@section('content')
<!-- KPIs -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @php
    $buyers = $newVsReturning['new'] + $newVsReturning['returning'];
    $newPct = $buyers > 0 ? round($newVsReturning['new'] / $buyers * 100) : 0;
    @endphp
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Total Customers</p>
        <p class="text-2xl font-bold text-slate-900 font-mono">{{ number_format($totalCustomers) }}</p>
        <p class="text-xs text-slate-400 mt-1">in database</p>
    </div>
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">New Customers</p>
        <p class="text-2xl font-bold text-green-700 font-mono">{{ number_format($newVsReturning['new']) }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ $newPct }}% of buyers</p>
    </div>
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Returning</p>
        <p class="text-2xl font-bold text-blue-700 font-mono">{{ number_format($newVsReturning['returning']) }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ 100 - $newPct }}% of buyers</p>
    </div>
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Customer Levels</p>
        <p class="text-2xl font-bold text-slate-900 font-mono">{{ count($levelStats) }}</p>
        <p class="text-xs text-slate-400 mt-1">tiers configured</p>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-800 mb-1">Gender Distribution</h3>
        <p class="text-xs text-slate-400 mb-4">Customer split by gender</p>
        <canvas id="genderChart" height="200"></canvas>
    </div>
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-800 mb-1">Order Frequency</h3>
        <p class="text-xs text-slate-400 mb-4">How many orders each customer placed</p>
        <canvas id="ageChart" height="200"></canvas>
    </div>
    <div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-800 mb-1">New vs Returning</h3>
        <p class="text-xs text-slate-400 mb-4">Customer loyalty split</p>
        <canvas id="loyaltyChart" height="200"></canvas>
    </div>
</div>

<!-- Customer Acquisition -->
<div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm mb-4">
    <h3 class="text-sm font-semibold text-slate-800 mb-1">Customer Acquisition by Month</h3>
    <p class="text-xs text-slate-400 mb-4">New customers (first order) per calendar month</p>
    <canvas id="birthdayChart" height="60"></canvas>
</div>

<!-- Bottom Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Top Provinces -->
    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800">Top Provinces by Customers</h3>
        </div>
        <table class="w-full">
            <thead><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50">Province</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Customers</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Total Spent</th>
            </tr></thead>
            <tbody>
                @forelse($topProvinces as $p)
                <tr class="border-t border-slate-100 hover:bg-blue-50/40">
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $p['province'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">{{ number_format($p['count']) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">₱{{ number_format($p['total_spent']) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No data yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Top Customers -->
    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800">Top Customers by Spend</h3>
        </div>
        <div class="overflow-y-auto max-h-72">
            <table class="w-full">
                <thead><tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50 sticky top-0">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50 sticky top-0">Level</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50 sticky top-0">Orders</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50 sticky top-0">Spent</th>
                </tr></thead>
                <tbody>
                    @forelse($topCustomers as $c)
                    <tr class="border-t border-slate-100 hover:bg-blue-50/40">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-slate-800">{{ $c['name'] ?? 'Unknown' }}</div>
                            <div class="text-xs text-slate-400">{{ $c['phone'] ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($c['customer_level'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ $c['customer_level'] }}</span>
                            @else<span class="text-xs text-slate-400">—</span>@endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">{{ $c['total_orders'] }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-900 text-right font-mono">₱{{ number_format($c['total_spent']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script id="gender-data" type="application/json"><?php echo json_encode($genderStats); ?></script>
<script id="age-data" type="application/json"><?php echo json_encode($ageStats); ?></script>
<script id="birthday-data" type="application/json"><?php echo json_encode($birthdayMonth); ?></script>
<script id="loyalty-data" type="application/json"><?php echo json_encode($newVsReturning); ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const genderData = JSON.parse(document.getElementById('gender-data').textContent);
const ageData = JSON.parse(document.getElementById('age-data').textContent);
const birthdayData = JSON.parse(document.getElementById('birthday-data').textContent);
const loyalty = JSON.parse(document.getElementById('loyalty-data').textContent);

const palette = ['#1E40AF','#3B82F6','#D97706','#10B981','#8B5CF6','#EC4899','#EF4444','#6B7280'];

new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: { labels: genderData.labels, datasets: [{ data: genderData.data, backgroundColor: palette.slice(0, genderData.labels.length), borderWidth: 2, borderColor: '#fff', hoverOffset: 4 }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 10 } } }, cutout: '60%' }
});

new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: { labels: ageData.labels, datasets: [{ label: 'Customers', data: ageData.data, backgroundColor: 'rgba(30,64,175,0.8)', borderRadius: 4 }] },
    options: { responsive: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => `${ctx.raw} customers` } } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } }, y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } } } }
});

new Chart(document.getElementById('loyaltyChart'), {
    type: 'doughnut',
    data: { labels: ['New', 'Returning'], datasets: [{ data: [loyalty.new, loyalty.returning], backgroundColor: ['#10B981','#1E40AF'], borderWidth: 2, borderColor: '#fff', hoverOffset: 4 }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 10 } } }, cutout: '60%' }
});

new Chart(document.getElementById('birthdayChart'), {
    type: 'bar',
    data: { labels: birthdayData.labels, datasets: [{ label: 'New Customers', data: birthdayData.data, backgroundColor: 'rgba(217,119,6,0.7)', borderRadius: 4 }] },
    options: { responsive: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => `${ctx.raw} new customers in ${ctx.label}` } } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 11 } } }, y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } } } }
});
</script>
@endpush
