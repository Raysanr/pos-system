@extends('layouts.app')
@section('title', 'RTS & Returns')
@section('subtitle', 'Return-to-sender analysis by province, courier, and trend')

@section('header_actions')
    @include('partials.filter-bar', [
        'filterRoute'   => route('analytics.rts'),
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
    <div class="bg-white rounded-xl border border-red-100 p-4 shadow-sm">
        <p class="text-xs font-semibold text-red-500 uppercase tracking-wider mb-2">RTS Count</p>
        <p class="text-xl font-bold text-red-700 font-mono">{{ number_format($rtsKpis['rts_count']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-red-100 p-4 shadow-sm">
        <p class="text-xs font-semibold text-red-500 uppercase tracking-wider mb-2">RTS Rate</p>
        <p class="text-xl font-bold text-red-700 font-mono">{{ $rtsKpis['rts_rate'] }}%</p>
    </div>
    <div class="bg-white rounded-xl border border-amber-100 p-4 shadow-sm">
        <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-2">Returned</p>
        <p class="text-xl font-bold text-amber-700 font-mono">{{ number_format($rtsKpis['returned']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-sm">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Revenue Lost</p>
        <p class="text-xl font-bold text-slate-900 font-mono">₱{{ number_format($rtsKpis['revenue_lost']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-red-100 p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider mb-2" style="color:#EF4444;">Bottles Lost</p>
        <p class="text-xl font-bold font-mono" style="color:#EF4444;">{{ number_format($rtsKpis['bottles_lost']) }}</p>
        <p class="mt-1 text-xs text-slate-400">RTS bottles</p>
    </div>
</div>

<!-- RTS Trend -->
<div class="bg-white rounded-xl border border-blue-100 p-5 shadow-sm mb-4">
    <h3 class="text-sm font-semibold text-slate-800 mb-1">RTS Trend</h3>
    <p class="text-xs text-slate-400 mb-4">Daily total orders vs RTS over selected period</p>
    <canvas id="rtsTrendChart" height="80"></canvas>
</div>

<!-- Province & Courier Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800">RTS by Province</h3>
            <p class="text-xs text-slate-400">Provinces with highest return rates</p>
        </div>
        <div class="overflow-y-auto max-h-80">
            <table class="w-full">
                <thead class="sticky top-0"><tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50">Province</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">RTS</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Rate</th>
                </tr></thead>
                <tbody>
                    @forelse($rtsByProvince as $r)
                    <tr class="border-t border-slate-100 hover:bg-red-50/30">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $r['province'] }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">{{ number_format($r['total']) }}</td>
                        <td class="px-4 py-3 text-sm text-red-600 text-right font-mono">{{ number_format($r['rts']) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $r['rts_rate'] > 25 ? 'bg-red-100 text-red-700' : ($r['rts_rate'] > 15 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }}">{{ $r['rts_rate'] }}%</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800">RTS by Courier</h3>
            <p class="text-xs text-slate-400">Compare courier performance</p>
        </div>
        <table class="w-full">
            <thead><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50">Courier</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Total</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">RTS</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Rate</th>
            </tr></thead>
            <tbody>
                @forelse($rtsByCourier as $c)
                <tr class="border-t border-slate-100 hover:bg-red-50/30">
                    <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ $c['courier'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right font-mono">{{ number_format($c['total']) }}</td>
                    <td class="px-4 py-3 text-sm text-red-600 text-right font-mono">{{ number_format($c['rts']) }}</td>
                    <td class="px-4 py-3 text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c['rts_rate'] > 25 ? 'bg-red-100 text-red-700' : ($c['rts_rate'] > 15 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }}">{{ $c['rts_rate'] }}%</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No data yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <!-- Courier Bar Chart -->
        @if(count($rtsByCourier) > 0)
        <div class="px-5 py-4 border-t border-slate-100">
            <canvas id="courierChart" height="120"></canvas>
        </div>
        @endif
    </div>
</div>

<!-- Return Reasons -->
@if(count($returnReasons) > 0)
<div class="bg-white rounded-xl border border-blue-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-800">Top Return Reasons</h3>
        <p class="text-xs text-slate-400">From Pancake return reason field</p>
    </div>
    <table class="w-full">
        <thead><tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase bg-slate-50">Reason</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase bg-slate-50">Count</th>
        </tr></thead>
        <tbody>
            @foreach($returnReasons as $r)
            <tr class="border-t border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3 text-sm text-slate-700">{{ $r['return_reason'] }}</td>
                <td class="px-4 py-3 text-sm font-semibold text-red-600 text-right font-mono">{{ $r['count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection

@push('scripts')
<script id="trend-data" type="application/json"><?php echo json_encode($rtsTrend); ?></script>
<script id="courier-data" type="application/json"><?php echo json_encode($rtsByCourier); ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trendData = JSON.parse(document.getElementById('trend-data').textContent);
const courierData = JSON.parse(document.getElementById('courier-data').textContent);

new Chart(document.getElementById('rtsTrendChart'), {
    type: 'bar',
    data: {
        labels: trendData.labels,
        datasets: [
            { label: 'Total Orders', data: trendData.total, backgroundColor: 'rgba(30,64,175,0.15)', borderColor: '#1E40AF', borderWidth: 1.5, borderRadius: 3, yAxisID: 'y' },
            { label: 'RTS', data: trendData.rts, type: 'line', borderColor: '#EF4444', backgroundColor: 'rgba(239,68,68,0.08)', borderWidth: 2, pointRadius: 2, tension: 0.4, fill: true, yAxisID: 'y' }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45 } },
            y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } }
        }
    }
});

@if(count($rtsByCourier) > 0)
new Chart(document.getElementById('courierChart'), {
    type: 'bar',
    data: {
        labels: courierData.map(c => c.courier),
        datasets: [
            { label: 'Total', data: courierData.map(c => c.total), backgroundColor: 'rgba(30,64,175,0.7)', borderRadius: 3 },
            { label: 'RTS', data: courierData.map(c => c.rts), backgroundColor: 'rgba(239,68,68,0.8)', borderRadius: 3 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 10 } } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } }
        }
    }
});
@endif
</script>
@endpush
