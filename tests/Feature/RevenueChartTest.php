<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PancakeShop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_chart_includes_placed_and_delivered_counts(): void
    {
        $user = User::factory()->create();
        $shop = PancakeShop::create([
            'user_id'   => $user->id,
            'shop_id'   => 'TEST001',
            'api_key'   => 'key',
            'is_active' => true,
        ]);

        $day = now()->subDays(2)->format('Y-m-d');

        // 1 delivered order (counts in revenue + orders + placed)
        Order::create([
            'shop_id'    => $shop->id,
            'pancake_id' => 'ORD001',
            'status'     => 'delivered',
            'total_price'=> 1000,
            'ordered_at' => $day . ' 10:00:00',
        ]);

        // 1 pending order (counts only in placed)
        Order::create([
            'shop_id'    => $shop->id,
            'pancake_id' => 'ORD002',
            'status'     => 'pending',
            'total_price'=> 500,
            'ordered_at' => $day . ' 11:00:00',
        ]);

        $this->actingAs($user);
        $response = $this->getJson(route('dashboard', [
            'date_from' => now()->subDays(7)->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $chart = $response->json('revenueChart');

        $this->assertArrayHasKey('labels',  $chart);
        $this->assertArrayHasKey('revenue', $chart);
        $this->assertArrayHasKey('orders',  $chart);
        $this->assertArrayHasKey('placed',  $chart);

        // Find the index for our test day
        $labelDay = \Carbon\Carbon::parse($day)->format('M d');
        $idx = array_search($labelDay, $chart['labels']);
        $this->assertNotFalse($idx, "Test day label '{$labelDay}' must appear in chart");

        $this->assertSame(1000, $chart['revenue'][$idx], 'Only delivered revenue counted');
        $this->assertSame(1,    $chart['orders'][$idx],  'Only delivered order counted');
        $this->assertSame(2,    $chart['placed'][$idx],  'Both placed orders counted');
    }
}
