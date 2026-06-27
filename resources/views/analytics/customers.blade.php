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
@php
$buyers  = $newVsReturning['new'] + $newVsReturning['returning'];
$newPct  = $buyers > 0 ? round($newVsReturning['new'] / $buyers * 100) : 0;
$retPct  = 100 - $newPct;
@endphp

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    <div class="kpi-card bg-white rounded-xl shadow-sm overflow-hidden" style="border:1px solid #DBEAFE; border-left:4px solid #1E40AF;">
        <div class="p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold uppercase tracking-wider" style="color:#64748B;">Total Customers</p>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(30,64,175,0.08);">
                    <svg class="w-4.5 h-4.5" style="width:18px;height:18px;color:#1E40AF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p id="kpi-total-customers" class="text-3xl font-bold font-mono" style="color:#0F172A;">{{ number_format($totalCustomers) }}</p>
            <p class="text-xs mt-1.5" style="color:#94A3B8;">in database</p>
        </div>
    </div>

    <div class="kpi-card bg-white rounded-xl shadow-sm overflow-hidden" style="border:1px solid #D1FAE5; border-left:4px solid #10B981;">
        <div class="p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold uppercase tracking-wider" style="color:#64748B;">New Customers</p>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(16,185,129,0.08);">
                    <svg style="width:18px;height:18px;color:#10B981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
            </div>
            <p id="kpi-new-customers" class="text-3xl font-bold font-mono" style="color:#10B981;">{{ number_format($newVsReturning['new']) }}</p>
            <p id="kpi-new-pct" class="text-xs mt-1.5" style="color:#94A3B8;">{{ $newPct }}% of buyers</p>
        </div>
    </div>

    <div class="kpi-card bg-white rounded-xl shadow-sm overflow-hidden" style="border:1px solid #DBEAFE; border-left:4px solid #F5A623;">
        <div class="p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold uppercase tracking-wider" style="color:#64748B;">Returning</p>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(245,166,35,0.08);">
                    <svg style="width:18px;height:18px;color:#F5A623;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
            </div>
            <p id="kpi-returning" class="text-3xl font-bold font-mono" style="color:#F5A623;">{{ number_format($newVsReturning['returning']) }}</p>
            <p id="kpi-ret-pct" class="text-xs mt-1.5" style="color:#94A3B8;">{{ $retPct }}% of buyers</p>
        </div>
    </div>

</div>

<!-- Charts Row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <div class="bg-white rounded-xl shadow-sm p-5 flex flex-col" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Gender Distribution</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">Customer split by gender</p>
        @php
            $genderTotal  = array_sum($genderStats['data']);
            $genderColors = ['#1E40AF','#F5A623','#94A3B8'];
        @endphp
        <div class="flex flex-1 items-center gap-6">
            {{-- Small donut with centered total --}}
            <div style="width:130px;height:130px;flex-shrink:0;position:relative;">
                <div class="chart-skeleton" id="sk-gender" style="position:absolute;inset:0;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <div class="sk-bar" style="width:130px;height:130px;border-radius:50%;"></div>
                </div>
                <canvas id="genderChart" class="chart-canvas" style="width:130px!important;height:130px!important;"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                    <span id="gender-total-label" class="text-base font-bold font-mono" style="color:#0F172A;">{{ number_format($genderTotal) }}</span>
                    <span style="font-size:10px;color:#94A3B8;">customers</span>
                </div>
            </div>
            {{-- Colored stat bars --}}
            <div id="gender-stats-list" class="flex-1 space-y-3">
                @foreach($genderStats['labels'] as $i => $label)
                @php
                    $val   = $genderStats['data'][$i] ?? 0;
                    $pct   = $genderTotal > 0 ? round($val / $genderTotal * 100) : 0;
                    $col   = $genderColors[$i] ?? '#94A3B8';
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-2 h-2 rounded-full" style="background:{{ $col }};"></span>
                            <span class="text-xs font-semibold" style="color:#334155;">{{ $label }}</span>
                        </div>
                        <span class="text-xs font-mono font-bold" style="color:{{ $col }};">{{ number_format($val) }} <span class="font-normal" style="color:#94A3B8;">{{ $pct }}%</span></span>
                    </div>
                    <div class="w-full rounded-full" style="height:5px;background:#F1F5F9;">
                        <div class="rounded-full transition-all duration-700" style="width:{{ $pct }}%;height:5px;background:{{ $col }};"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Order Frequency</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">How many orders each customer placed</p>
        <div class="chart-wrap" style="min-height:200px;">
            <div class="chart-skeleton" id="sk-freq">
                <div style="display:flex;align-items:flex-end;gap:8px;height:160px;padding-top:20px;">
                    <div class="sk-bar" style="flex:1;height:80%;"></div>
                    <div class="sk-bar" style="flex:1;height:30%;"></div>
                    <div class="sk-bar" style="flex:1;height:15%;"></div>
                    <div class="sk-bar" style="flex:1;height:8%;"></div>
                    <div class="sk-bar" style="flex:1;height:5%;"></div>
                </div>
            </div>
            <canvas id="ageChart" class="chart-canvas" height="200"></canvas>
        </div>
    </div>

</div>

<!-- Age Groups + Health Conditions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Age Groups</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">Distribution of customer ages (from order notes)</p>
        @if(array_sum($ageGroups['data']) > 0)
        <div class="chart-wrap" style="min-height:180px;">
            <div class="chart-skeleton" id="sk-age-groups">
                <div style="display:flex;align-items:flex-end;gap:10px;height:160px;padding-top:20px;">
                    @foreach([40,60,80,70,50,30] as $h)
                    <div class="sk-bar" style="flex:1;height:{{ $h }}%;"></div>
                    @endforeach
                </div>
            </div>
            <canvas id="ageGroupsChart" class="chart-canvas" height="180"></canvas>
        </div>
        @else
        <div class="empty-state" style="min-height:160px;">
            <div class="empty-state-icon">
                <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <p class="title">No age data yet</p>
            <p class="hint">Run <code style="font-family:'Fira Code',monospace;font-size:11px;background:#F1F5F9;padding:1px 4px;border-radius:3px;">app:extract-order-demographics</code> to extract age from notes.</p>
        </div>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Health Conditions</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">Extracted from order notes</p>
        @if(count($healthConditions['labels']) > 0)
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
        @else
        <div class="empty-state" style="min-height:180px;">
            <div class="empty-state-icon">
                <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="title">No health conditions found</p>
            <p class="hint">Run <code style="font-family:'Fira Code',monospace;font-size:11px;background:#F1F5F9;padding:1px 4px;border-radius:3px;">app:extract-order-demographics</code> to extract from notes.</p>
        </div>
        @endif
    </div>

</div>

<!-- Customer Acquisition -->
<div class="bg-white rounded-xl shadow-sm p-5 mb-4" style="border:1px solid #DBEAFE;">
    <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Customer Acquisition by Month</h3>
    <p class="text-xs mb-4" style="color:#94A3B8;">New customers (first order) per calendar month</p>
    <div class="chart-wrap" style="min-height:80px;">
        <div class="chart-skeleton" id="sk-acq">
            <div style="display:flex;align-items:flex-end;gap:6px;height:70px;">
                @foreach(range(1,12) as $i)
                <div class="sk-bar" style="flex:1;height:{{ rand(20,90) }}%;"></div>
                @endforeach
            </div>
        </div>
        <canvas id="birthdayChart" class="chart-canvas" height="60"></canvas>
    </div>
</div>

<!-- Bottom Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Top Provinces -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden flex flex-col" style="border:1px solid #DBEAFE;">
        <div class="px-5 py-4 flex-none" style="border-bottom:1px solid #F1F5F9;">
            <h3 class="text-sm font-semibold" style="color:#1E293B;">Top Provinces by Customers</h3>
        </div>
        <div class="overflow-y-auto flex-1" style="max-height:480px;">
        <table class="w-full">
            <thead>
                <tr style="background:#F8FAFC;">
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Province</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Customers</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Total Spent</th>
                </tr>
            </thead>
            <tbody id="tbody-provinces">
                @forelse($topProvinces as $p)
                <tr style="border-top:1px solid #F1F5F9;" onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                    <td class="px-4 py-3 text-sm font-medium" style="color:#334155;">{{ $p['province'] }}</td>
                    <td class="px-4 py-3 text-sm text-right font-mono" style="color:#475569;">{{ number_format($p['count']) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-semibold" style="color:#1E40AF;">₱{{ number_format($p['total_spent']) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                            </div>
                            <p class="title">No province data yet</p>
                            <p class="hint">Sync your orders to populate location data.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>{{-- /overflow-y-auto --}}
    </div>

    <!-- Top Customers -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden flex flex-col" style="border:1px solid #DBEAFE;">
        <div class="px-5 py-4 flex-none" style="border-bottom:1px solid #F1F5F9;">
            <h3 class="text-sm font-semibold" style="color:#1E293B;">Top Customers by Spend</h3>
        </div>
        <div class="overflow-y-auto flex-1" style="max-height:480px;">
            <table class="w-full">
                <thead>
                    <tr style="background:#F8FAFC; position:sticky; top:0; z-index:1;">
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Customer</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Orders</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Spent</th>
                    </tr>
                </thead>
                <tbody id="tbody-customers">
                    @forelse($topCustomers as $c)
                    <tr style="border-top:1px solid #F1F5F9;" onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium" style="color:#1E293B;">{{ $c['name'] ?? 'Unknown' }}</div>
                            <div class="text-xs" style="color:#94A3B8;">{{ $c['phone'] ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-mono" style="color:#475569;">{{ $c['total_orders'] }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-semibold" style="color:#1E40AF;">₱{{ number_format($c['total_spent']) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <p class="title">No customers yet</p>
                                <p class="hint">Sync orders to see top spenders here.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ── Ordering Patterns ───────────────────────────────────────────── -->
<div class="mt-8 mb-3 flex items-center gap-3">
    <p class="text-xs font-bold uppercase tracking-widest" style="color:#94A3B8;">Ordering Patterns</p>
    <div class="flex-1 h-px" style="background:#E2E8F0;"></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Peak Order Days</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">Which day of the week gets the most orders</p>
        <div class="chart-wrap" style="min-height:180px;">
            <div class="chart-skeleton" id="sk-days">
                <div style="display:flex;align-items:flex-end;gap:8px;height:160px;padding-top:20px;">
                    @foreach([60,80,70,90,85,65,40] as $h)
                    <div class="sk-bar" style="flex:1;height:{{ $h }}%;"></div>
                    @endforeach
                </div>
            </div>
            <canvas id="peakDaysChart" class="chart-canvas" height="180"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5" style="border:1px solid #DBEAFE;">
        <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Peak Order Hours</h3>
        <p class="text-xs mb-4" style="color:#94A3B8;">What time of day customers place orders most</p>
        <div class="chart-wrap" style="min-height:280px;display:flex;align-items:center;justify-content:center;">
            <div class="chart-skeleton" id="sk-hours" style="width:260px;height:260px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <div class="sk-bar" style="width:260px;height:260px;border-radius:50%;"></div>
            </div>
            <canvas id="peakHoursChart" class="chart-canvas" style="max-width:300px;max-height:300px;"></canvas>
        </div>
    </div>

</div>

<div class="bg-white rounded-xl shadow-sm p-5 mb-4" style="border:1px solid #DBEAFE;">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h3 class="text-sm font-semibold mb-0.5" style="color:#1E293B;">Basket Size</h3>
            <p class="text-xs" style="color:#94A3B8;">Bottles per order — upsell opportunity</p>
        </div>
        <div class="text-right">
            <p id="kpi-basket-avg" class="text-2xl font-bold font-mono" style="color:#1E40AF;">{{ $basketSize['avg'] }}</p>
            <p class="text-xs mt-0.5" style="color:#94A3B8;">avg bottles / order</p>
        </div>
    </div>
    <div class="chart-wrap" style="min-height:100px;">
        <div class="chart-skeleton" id="sk-basket">
            <div style="display:flex;align-items:flex-end;gap:16px;height:90px;padding-top:10px;">
                @foreach([90,40,20,12,8] as $h)
                <div class="sk-bar" style="flex:1;height:{{ $h }}%;"></div>
                @endforeach
            </div>
        </div>
        <canvas id="basketChart" class="chart-canvas" height="90"></canvas>
    </div>
</div>

<!-- ── Product Behavior ────────────────────────────────────────────── -->
<div class="mt-8 mb-3 flex items-center gap-3">
    <p class="text-xs font-bold uppercase tracking-widest" style="color:#94A3B8;">Product Behavior</p>
    <div class="flex-1 h-px" style="background:#E2E8F0;"></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="border:1px solid #DBEAFE;">
        <div class="px-5 py-4" style="border-bottom:1px solid #F1F5F9;">
            <h3 class="text-sm font-semibold" style="color:#1E293B;">First Product Purchased</h3>
            <p class="text-xs mt-0.5" style="color:#94A3B8;">Top entry-point products for new customers</p>
        </div>
        <table class="w-full">
            <thead>
                <tr style="background:#F8FAFC;">
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Product</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">First-time Buyers</th>
                </tr>
            </thead>
            <tbody id="tbody-first-products">
                @forelse($firstProducts as $i => $fp)
                <tr style="border-top:1px solid #F1F5F9;" onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                    <td class="px-4 py-3">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold font-mono"
                             style="background:{{ $i < 3 ? 'rgba(30,64,175,0.1)' : '#F1F5F9' }}; color:{{ $i < 3 ? '#1E40AF' : '#64748B' }};">
                            {{ $i + 1 }}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm font-medium" style="color:#1E293B;">{{ ucwords($fp['product_name']) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-semibold" style="color:#1E40AF;">{{ number_format($fp['first_buyers']) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                            </div>
                            <p class="title">No data yet</p>
                            <p class="hint">Sync orders to see first product data.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="border:1px solid #DBEAFE;">
        <div class="px-5 py-4" style="border-bottom:1px solid #F1F5F9;">
            <h3 class="text-sm font-semibold" style="color:#1E293B;">Product Affinity</h3>
            <p class="text-xs mt-0.5" style="color:#94A3B8;">Products most often bought together in the same order (delivered orders only)</p>
        </div>
        <table class="w-full">
            <thead>
                <tr style="background:#F8FAFC;">
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Product A</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">+</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Product B</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide" style="color:#64748B;">Orders</th>
                </tr>
            </thead>
            <tbody id="tbody-affinity">
                @forelse($productAffinity as $pa)
                <tr style="border-top:1px solid #F1F5F9;" onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                    <td class="px-4 py-3 text-sm font-medium" style="color:#1E293B;">{{ ucwords($pa['product_a']) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold" style="background:rgba(245,166,35,0.12); color:#D97706;">+</span>
                    </td>
                    <td class="px-4 py-3 text-sm font-medium" style="color:#1E293B;">{{ ucwords($pa['product_b']) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-semibold" style="color:#F5A623;">{{ number_format($pa['pair_count']) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg style="width:22px;height:22px;color:#94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <p class="title">No affinity data yet</p>
                            <p class="hint">Customers need to buy multiple products in one order.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection

@push('scripts')
<script id="gender-data"     type="application/json"><?php echo json_encode($genderStats); ?></script>
<script id="age-data"        type="application/json"><?php echo json_encode($ageStats); ?></script>
<script id="age-groups-data" type="application/json"><?php echo json_encode($ageGroups); ?></script>
<script id="health-data"     type="application/json"><?php echo json_encode($healthConditions); ?></script>
<script id="birthday-data"   type="application/json"><?php echo json_encode($birthdayMonth); ?></script>
<script id="peak-days-data"  type="application/json"><?php echo json_encode($peakPatterns['days']); ?></script>
<script id="peak-hours-data" type="application/json"><?php echo json_encode($peakPatterns['hours']); ?></script>
<script id="basket-data"     type="application/json"><?php echo json_encode($basketSize['distribution']); ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const genderData     = JSON.parse(document.getElementById('gender-data').textContent);
const ageData        = JSON.parse(document.getElementById('age-data').textContent);
const ageGroupsData  = JSON.parse(document.getElementById('age-groups-data').textContent);
const healthData     = JSON.parse(document.getElementById('health-data').textContent);
const birthdayData   = JSON.parse(document.getElementById('birthday-data').textContent);

function revealChart(canvasId, skeletonId) {
    const canvas = document.getElementById(canvasId);
    const sk     = document.getElementById(skeletonId);
    if (canvas) canvas.classList.add('loaded');
    if (sk) sk.style.display = 'none';
}

const BLUE   = '#1E40AF';
const LBLUE  = '#3B82F6';
const GOLD   = '#F5A623';
const GREEN  = '#10B981';
const SLATE  = '#94A3B8';
const VIOLET = '#8B5CF6';
const palette   = [BLUE, GOLD, GREEN, VIOLET, LBLUE, SLATE, '#EC4899', '#EF4444'];
const gridColor = '#F1F5F9';
const tickFont  = { size: 11, family: "'Fira Code', monospace" };
const tickColor = '#64748B';

window.__charts = {};

// Gender
window.__charts.gender = new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: genderData.labels,
        datasets: [{ data: genderData.data, backgroundColor: palette.slice(0, genderData.labels.length), borderWidth: 3, borderColor: '#fff', hoverOffset: 5 }]
    },
    options: {
        responsive: false, maintainAspectRatio: false, cutout: '70%',
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw.toLocaleString()}` } } }
    }
});
revealChart('genderChart', 'sk-gender');

// Order Frequency
window.__charts.age = new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: { labels: ageData.labels, datasets: [{ label: 'Customers', data: ageData.data, backgroundColor: BLUE, borderRadius: 5, borderSkipped: false }] },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString()} customers` } } },
        scales: { x: { grid: { display: false }, ticks: { font: tickFont, color: tickColor } }, y: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor } } }
    }
});
revealChart('ageChart', 'sk-freq');

// Acquisition by Month
window.__charts.birthday = new Chart(document.getElementById('birthdayChart'), {
    type: 'bar',
    data: { labels: birthdayData.labels, datasets: [{ label: 'New Customers', data: birthdayData.data, backgroundColor: GOLD, borderRadius: 5, borderSkipped: false }] },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString()} new in ${ctx.label}` } } },
        scales: { x: { grid: { display: false }, ticks: { font: tickFont, color: tickColor } }, y: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor } } }
    }
});
revealChart('birthdayChart', 'sk-acq');

// Age Groups
if (ageGroupsData.data.some(v => v > 0)) {
    window.__charts.ageGroups = new Chart(document.getElementById('ageGroupsChart'), {
        type: 'bar',
        data: {
            labels: ageGroupsData.labels,
            datasets: [{ data: ageGroupsData.data, backgroundColor: GOLD, borderRadius: 6, borderSkipped: false }]
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
    revealChart('ageGroupsChart', 'sk-age-groups');
}

// Health Conditions
if (healthData.labels.length > 0) {
    window.__charts.health = new Chart(document.getElementById('healthChart'), {
        type: 'bar',
        data: {
            labels: healthData.labels,
            datasets: [{ data: healthData.data, backgroundColor: '#0F172A', borderRadius: 4, borderSkipped: false }]
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
}

const peakDaysData  = JSON.parse(document.getElementById('peak-days-data').textContent);
const peakHoursData = JSON.parse(document.getElementById('peak-hours-data').textContent);
const basketData    = JSON.parse(document.getElementById('basket-data').textContent);

// Peak Order Days
window.__charts.peakDays = new Chart(document.getElementById('peakDaysChart'), {
    type: 'bar',
    data: {
        labels: peakDaysData.labels,
        datasets: [{ data: peakDaysData.data, backgroundColor: peakDaysData.data.map(v => {
            const max = Math.max(...peakDaysData.data);
            return v === max ? BLUE : 'rgba(30,64,175,0.25)';
        }), borderRadius: 6, borderSkipped: false }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString()} orders` } } },
        scales: { x: { grid: { display: false }, ticks: { font: tickFont, color: tickColor } }, y: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor } } }
    }
});
revealChart('peakDaysChart', 'sk-days');

// Peak Order Hours — polar area
(function () {
    const maxHr = Math.max(...peakHoursData.data);
    window.__charts.peakHours = new Chart(document.getElementById('peakHoursChart'), {
        type: 'polarArea',
        data: {
            labels: peakHoursData.labels,
            datasets: [{ data: peakHoursData.data, backgroundColor: peakHoursData.data.map(v => {
                const t = maxHr > 0 ? v / maxHr : 0;
                return `rgba(245,166,35,${(0.15 + t * 0.85).toFixed(2)})`;
            }), borderColor: '#fff', borderWidth: 2 }]
        },
        options: {
            responsive: true, maintainAspectRatio: true, startAngle: -(Math.PI / 2),
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString()} orders at ${ctx.label}` } } },
            scales: { r: { grid: { color: 'rgba(148,163,184,0.15)' }, ticks: { display: false, backdropColor: 'transparent' }, pointLabels: { display: true, font: { size: 9, family: "'Fira Code', monospace" }, color: '#64748B' } } }
        }
    });
    revealChart('peakHoursChart', 'sk-hours');
})();

// Basket Size Distribution
window.__charts.basket = new Chart(document.getElementById('basketChart'), {
    type: 'bar',
    data: { labels: basketData.labels, datasets: [{ data: basketData.data, backgroundColor: GREEN, borderRadius: 6, borderSkipped: false }] },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString()} orders` } } },
        scales: { x: { grid: { display: false }, ticks: { font: tickFont, color: tickColor } }, y: { grid: { color: gridColor }, ticks: { font: tickFont, color: tickColor } } }
    }
});
revealChart('basketChart', 'sk-basket');

// ── AJAX filter update ────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

window.__ajaxFilterUpdate = async function (form, params, url) {
    const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const d = await res.json();

    // KPIs
    document.getElementById('kpi-total-customers').textContent = Number(d.kpis.totalCustomers).toLocaleString();
    document.getElementById('kpi-new-customers').textContent   = Number(d.kpis.newCustomers).toLocaleString();
    document.getElementById('kpi-new-pct').textContent         = d.kpis.newPct + '% of buyers';
    document.getElementById('kpi-returning').textContent       = Number(d.kpis.returning).toLocaleString();
    document.getElementById('kpi-ret-pct').textContent         = d.kpis.retPct + '% of buyers';
    document.getElementById('kpi-basket-avg').textContent      = d.kpis.basketAvg;

    // Gender donut + stat bars
    const gTotal = d.genderStats.data.reduce((a, b) => a + b, 0);
    document.getElementById('gender-total-label').textContent = gTotal.toLocaleString();
    window.__charts.gender.data.labels = d.genderStats.labels;
    window.__charts.gender.data.datasets[0].data = d.genderStats.data;
    window.__charts.gender.data.datasets[0].backgroundColor = palette.slice(0, d.genderStats.labels.length);
    window.__charts.gender.update();
    const gColors = ['#1E40AF', '#F5A623', '#94A3B8'];
    document.getElementById('gender-stats-list').innerHTML = d.genderStats.labels.map((label, i) => {
        const val = d.genderStats.data[i] || 0;
        const pct = gTotal > 0 ? Math.round(val / gTotal * 100) : 0;
        const col = gColors[i] || '#94A3B8';
        return `<div>
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-2 h-2 rounded-full" style="background:${col}"></span>
                    <span class="text-xs font-semibold" style="color:#334155">${escHtml(label)}</span>
                </div>
                <span class="text-xs font-mono font-bold" style="color:${col}">${val.toLocaleString()} <span class="font-normal" style="color:#94A3B8">${pct}%</span></span>
            </div>
            <div class="w-full rounded-full" style="height:5px;background:#F1F5F9">
                <div class="rounded-full transition-all duration-700" style="width:${pct}%;height:5px;background:${col}"></div>
            </div>
        </div>`;
    }).join('');

    // Order Frequency chart
    window.__charts.age.data.labels = d.ageStats.labels;
    window.__charts.age.data.datasets[0].data = d.ageStats.data;
    window.__charts.age.update();

    // Age Groups chart
    if (window.__charts.ageGroups) {
        window.__charts.ageGroups.data.labels = d.ageGroups.labels;
        window.__charts.ageGroups.data.datasets[0].data = d.ageGroups.data;
        window.__charts.ageGroups.update();
    }

    // Health Conditions chart
    if (window.__charts.health) {
        window.__charts.health.data.labels = d.healthConditions.labels;
        window.__charts.health.data.datasets[0].data = d.healthConditions.data;
        window.__charts.health.update();
    }

    // Acquisition by Month chart
    window.__charts.birthday.data.labels = d.birthdayMonth.labels;
    window.__charts.birthday.data.datasets[0].data = d.birthdayMonth.data;
    window.__charts.birthday.update();

    // Peak days — recompute highlight colour
    const maxDay = Math.max(...d.peakDays.data, 0);
    window.__charts.peakDays.data.labels = d.peakDays.labels;
    window.__charts.peakDays.data.datasets[0].data = d.peakDays.data;
    window.__charts.peakDays.data.datasets[0].backgroundColor = d.peakDays.data.map(v => v === maxDay ? BLUE : 'rgba(30,64,175,0.25)');
    window.__charts.peakDays.update();

    // Peak hours — recompute opacity gradient
    const maxHr = Math.max(...d.peakHours.data, 0);
    window.__charts.peakHours.data.labels = d.peakHours.labels;
    window.__charts.peakHours.data.datasets[0].data = d.peakHours.data;
    window.__charts.peakHours.data.datasets[0].backgroundColor = d.peakHours.data.map(v => {
        const t = maxHr > 0 ? v / maxHr : 0;
        return `rgba(245,166,35,${(0.15 + t * 0.85).toFixed(2)})`;
    });
    window.__charts.peakHours.update();

    // Basket distribution chart
    window.__charts.basket.data.labels = d.basketDist.labels;
    window.__charts.basket.data.datasets[0].data = d.basketDist.data;
    window.__charts.basket.update();

    // Top Provinces table
    document.getElementById('tbody-provinces').innerHTML = d.topProvinces.length
        ? d.topProvinces.map(p => `
            <tr style="border-top:1px solid #F1F5F9" onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                <td class="px-4 py-3 text-sm font-medium" style="color:#334155">${escHtml(p.province)}</td>
                <td class="px-4 py-3 text-sm text-right font-mono" style="color:#475569">${Number(p.count).toLocaleString()}</td>
                <td class="px-4 py-3 text-sm text-right font-mono font-semibold" style="color:#1E40AF">₱${Number(p.total_spent).toLocaleString()}</td>
            </tr>`).join('')
        : `<tr><td colspan="3"><div class="empty-state"><p class="title">No province data yet</p><p class="hint">Sync your orders to populate location data.</p></div></td></tr>`;

    // Top Customers table
    document.getElementById('tbody-customers').innerHTML = d.topCustomers.length
        ? d.topCustomers.map(c => `
            <tr style="border-top:1px solid #F1F5F9" onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                <td class="px-4 py-3">
                    <div class="text-sm font-medium" style="color:#1E293B">${escHtml(c.name || 'Unknown')}</div>
                    <div class="text-xs" style="color:#94A3B8">${escHtml(c.phone || '')}</div>
                </td>
                <td class="px-4 py-3 text-sm text-right font-mono" style="color:#475569">${c.total_orders}</td>
                <td class="px-4 py-3 text-sm text-right font-mono font-semibold" style="color:#1E40AF">₱${Number(c.total_spent).toLocaleString()}</td>
            </tr>`).join('')
        : `<tr><td colspan="3"><div class="empty-state"><p class="title">No customers yet</p><p class="hint">Sync orders to see top spenders here.</p></div></td></tr>`;

    // First Product Purchased table
    document.getElementById('tbody-first-products').innerHTML = d.firstProducts.length
        ? d.firstProducts.map((fp, i) => `
            <tr style="border-top:1px solid #F1F5F9" onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                <td class="px-4 py-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold font-mono"
                         style="background:${i < 3 ? 'rgba(30,64,175,0.1)' : '#F1F5F9'};color:${i < 3 ? '#1E40AF' : '#64748B'}">${i + 1}</div>
                </td>
                <td class="px-4 py-3 text-sm font-medium" style="color:#1E293B">${escHtml((fp.product_name || '').replace(/\b\w/g, c => c.toUpperCase()))}</td>
                <td class="px-4 py-3 text-sm text-right font-mono font-semibold" style="color:#1E40AF">${Number(fp.first_buyers).toLocaleString()}</td>
            </tr>`).join('')
        : `<tr><td colspan="3"><div class="empty-state"><p class="title">No data yet</p><p class="hint">Sync orders to see first product data.</p></div></td></tr>`;

    // Product Affinity table
    document.getElementById('tbody-affinity').innerHTML = d.productAffinity.length
        ? d.productAffinity.map(pa => `
            <tr style="border-top:1px solid #F1F5F9" onmouseover="this.style.background='#F0F7FF'" onmouseout="this.style.background=''">
                <td class="px-4 py-3 text-sm font-medium" style="color:#1E293B">${escHtml((pa.product_a || '').replace(/\b\w/g, c => c.toUpperCase()))}</td>
                <td class="px-4 py-3 text-center"><span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold" style="background:rgba(245,166,35,0.12);color:#D97706">+</span></td>
                <td class="px-4 py-3 text-sm font-medium" style="color:#1E293B">${escHtml((pa.product_b || '').replace(/\b\w/g, c => c.toUpperCase()))}</td>
                <td class="px-4 py-3 text-sm text-right font-mono font-semibold" style="color:#F5A623">${Number(pa.pair_count).toLocaleString()}</td>
            </tr>`).join('')
        : `<tr><td colspan="4"><div class="empty-state"><p class="title">No affinity data yet</p><p class="hint">Customers need to buy multiple products in one order.</p></div></td></tr>`;
};
</script>
@endpush
