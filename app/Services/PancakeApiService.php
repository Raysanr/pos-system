<?php
namespace App\Services;

use App\Models\PancakeShop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PancakeApiService
{
    private const BASE_URL = 'https://pos.pages.fm/api/v1';
    private const PER_PAGE = 100;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $shopId = '',
    ) {}

    public static function forShop(PancakeShop $shop): self
    {
        return new self($shop->api_key, $shop->shop_id);
    }

    /** Called with only an API key — returns all shops linked to that key. */
    public static function withKey(string $apiKey): self
    {
        return new self($apiKey);
    }

    /**
     * Detect all shops from an API key alone.
     * Returns ['success' => true, 'shops' => [...]] or ['success' => false, 'message' => '...']
     */
    public function detectShops(): array
    {
        $response = Http::timeout(15)
            ->get(self::BASE_URL . '/shops', ['api_key' => $this->apiKey]);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Invalid API key or connection failed.',
            ];
        }

        $body = $response->json();
        $shops = $body['shops'] ?? $body['data'] ?? (is_array($body) && isset($body[0]) ? $body : []);

        if (empty($shops)) {
            return ['success' => false, 'message' => 'No shops found for this API key.'];
        }

        return [
            'success' => true,
            'shops'   => array_map(fn($s) => [
                'id'     => (string)($s['id'] ?? $s['shop_id'] ?? ''),
                'name'   => $s['name'] ?? $s['shop_name'] ?? 'Unnamed Shop',
                'avatar' => $s['avatar'] ?? $s['logo'] ?? null,
                'raw'    => $s,
            ], $shops),
        ];
    }

    public function testConnection(): array
    {
        $response = $this->get("/shops/{$this->shopId}/customers", ['page' => 1, 'page_size' => 1]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Connected successfully'];
        }

        return ['success' => false, 'message' => $response->json('message') ?? 'Connection failed'];
    }

    public function getCustomers(int $page = 1): array
    {
        $response = $this->get("/shops/{$this->shopId}/customers", [
            'page' => $page,
            'page_size' => self::PER_PAGE,
        ]);

        return $this->parseResponse($response);
    }

    public function getAllCustomers(callable $onBatch = null): \Generator
    {
        $page = 1;
        do {
            $result = $this->getCustomers($page);
            $items = $result['data'] ?? [];

            if (empty($items)) break;

            if ($onBatch) $onBatch($items, $page);
            yield $items;

            $page++;
            $hasMore = count($items) === self::PER_PAGE;

            if ($hasMore) usleep(200000); // respect rate limit
        } while ($hasMore);
    }

    public function getOrders(int $page = 1, array $filters = []): array
    {
        $response = $this->get("/shops/{$this->shopId}/orders", array_merge([
            'page' => $page,
            'page_size' => self::PER_PAGE,
        ], $filters));

        return $this->parseResponse($response);
    }

    public function getAllOrders(callable $onBatch = null, array $filters = []): \Generator
    {
        $page = 1;
        do {
            $result = $this->getOrders($page, $filters);
            $items = $result['data'] ?? [];

            if (empty($items)) break;

            if ($onBatch) $onBatch($items, $page);
            yield $items;

            $page++;
            $hasMore = count($items) === self::PER_PAGE;

            if ($hasMore) usleep(200000);
        } while ($hasMore);
    }

    public function getStatistics(array $params = []): array
    {
        $response = $this->get("/shops/{$this->shopId}/statistics", $params);
        return $this->parseResponse($response);
    }

    public function getShopInfo(): array
    {
        $response = $this->get("/shops/{$this->shopId}");
        return $this->parseResponse($response);
    }

    private function get(string $endpoint, array $params = []): Response
    {
        return Http::withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
            ->timeout(30)
            ->retry(3, 500)
            ->get(self::BASE_URL . $endpoint, array_merge($params, [
                'api_key' => $this->apiKey,
            ]));
    }

    private function parseResponse(Response $response): array
    {
        if ($response->failed()) {
            Log::warning('Pancake API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['data' => [], 'error' => $response->json('message') ?? 'API error'];
        }

        return $response->json() ?? ['data' => []];
    }
}
