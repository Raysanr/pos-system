<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PancakeShop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapReturnedFilterTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrders(): array
    {
        $user = User::factory()->create();
        $shop = PancakeShop::create([
            'user_id'   => $user->id,
            'shop_id'   => 'TEST001',
            'api_key'   => 'key',
            'is_active' => true,
        ]);

        // RTS-only: is_rts=true, is_returned=false (Pancake status 4)
        Order::create([
            'shop_id'     => $shop->id,
            'pancake_id'  => 'ORD001',
            'province'    => 'Cebu',
            'is_rts'      => true,
            'is_returned' => false,
            'is_cancelled'=> false,
            'status'      => 'rts',
            'total_price' => 500,
            'ordered_at'  => now()->subDays(5),
        ]);

        // Confirmed returned: is_rts=true, is_returned=true (Pancake status 5)
        Order::create([
            'shop_id'     => $shop->id,
            'pancake_id'  => 'ORD002',
            'province'    => 'Metro Manila',
            'is_rts'      => true,
            'is_returned' => true,
            'is_cancelled'=> false,
            'status'      => 'returned',
            'total_price' => 750,
            'ordered_at'  => now()->subDays(3),
        ]);

        return [$user, $shop];
    }

    public function test_returned_filter_shows_only_is_returned_orders(): void
    {
        [$user] = $this->seedOrders();
        $this->actingAs($user);

        $response = $this->getJson(route('analytics.map', [
            'status'    => 'returned',
            'level'     => 'province',
            'date_from' => now()->subDays(30)->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $data = $response->json();

        // Only Metro Manila (is_returned=true). Cebu (is_rts only) must be excluded.
        $this->assertCount(1, $data);
        $this->assertEquals('Metro Manila', $data[0]['name']);
    }

    public function test_rts_filter_shows_all_is_rts_orders(): void
    {
        [$user] = $this->seedOrders();
        $this->actingAs($user);

        $response = $this->getJson(route('analytics.map', [
            'status'    => 'rts',
            'level'     => 'province',
            'date_from' => now()->subDays(30)->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $data = $response->json();

        // Both provinces have is_rts=true.
        $this->assertCount(2, $data);
        $names = array_column($data, 'name');
        $this->assertContains('Cebu', $names);
        $this->assertContains('Metro Manila', $names);
    }

    public function test_response_includes_returned_count_and_rate_fields(): void
    {
        [$user] = $this->seedOrders();
        $this->actingAs($user);

        $response = $this->getJson(route('analytics.map', [
            'status'    => 'all',
            'level'     => 'province',
            'date_from' => now()->subDays(30)->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('returned_count', $data[0]);
        $this->assertArrayHasKey('returned_rate', $data[0]);
    }
}
