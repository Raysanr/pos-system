<?php
namespace App\Services;

use App\Models\PancakeShop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PancakeApiService
{
    private const BASE_URL = 'https://pos.pages.fm/api/v1';
    public const  PER_PAGE = 50; // public so jobs can compare batch size vs. page size

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

    /**
     * Yields [$page => $items] so callers can track which page was processed.
     * $startPage lets a resumable job continue from where it left off.
     */
    public function getAllCustomers(?callable $onBatch = null, int $startPage = 1): \Generator
    {
        $page = $startPage;
        do {
            $result = $this->getCustomers($page);
            $items = $result['data'] ?? [];

            if (empty($items)) break;

            if ($onBatch) $onBatch($items, $page);
            yield $page => $items;

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

    /**
     * Yields [$page => $items] so callers can track which page was processed.
     * $startPage lets a resumable job continue from where it left off.
     */
    public function getAllOrders(?callable $onBatch = null, array $filters = [], int $startPage = 1): \Generator
    {
        $page = $startPage;
        do {
            $result = $this->getOrders($page, $filters);
            $items = $result['data'] ?? [];

            if (empty($items)) break;

            if ($onBatch) $onBatch($items, $page);
            yield $page => $items;

            $page++;
            $hasMore = count($items) === self::PER_PAGE;

            if ($hasMore) usleep(200000);
        } while ($hasMore);
    }

    /**
     * Fetch multiple order pages concurrently via Http::pool().
     * Returns array keyed by page number.
     * Value is array of items on success, or null if the request failed (vs [] for a truly empty page).
     */
    public function getOrderPagesBatch(array $pages, array $filters = []): array
    {
        if (empty($pages)) return [];

        $baseUrl = self::BASE_URL . "/shops/{$this->shopId}/orders";
        $apiKey  = $this->apiKey;
        $perPage = self::PER_PAGE;

        $responses = Http::pool(function ($pool) use ($pages, $filters, $baseUrl, $apiKey, $perPage) {
            $requests = [];
            foreach ($pages as $page) {
                $requests[] = $pool->as("p{$page}")
                    ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                    ->connectTimeout(15)
                    ->timeout(120)
                    ->withOptions([
                        CURLOPT_LOW_SPEED_LIMIT => 10,
                        CURLOPT_LOW_SPEED_TIME  => 60,
                    ])
                    ->get($baseUrl, array_merge($filters, [
                        'page'      => $page,
                        'page_size' => $perPage,
                        'api_key'   => $apiKey,
                    ]));
            }
            return $requests;
        });

        $result = [];
        foreach ($pages as $page) {
            $resp = $responses["p{$page}"] ?? null;
            if ($resp instanceof Response && $resp->successful()) {
                $data          = $resp->json('data');
                $result[$page] = is_array($data) ? $data : [];
            } else {
                // null = fetch failed; caller can distinguish from [] (no more data)
                if ($resp instanceof \Throwable) {
                    Log::warning("Parallel fetch failed page {$page}: {$resp->getMessage()}");
                } elseif ($resp instanceof Response) {
                    Log::warning("Parallel fetch HTTP {$resp->status()} on page {$page}");
                }
                $result[$page] = null;
            }
        }

        return $result;
    }

    /**
     * Fetch a single order by its Pancake ID.
     * Returns the raw order array or null if not found / API error.
     */
    public function getOrderById(string $orderId): ?array
    {
        $response = $this->get("/shops/{$this->shopId}/orders/{$orderId}");
        if ($response->failed()) return null;
        $body = $response->json();
        // Pancake may wrap the order in a 'data' key or return it directly.
        $order = $body['data'] ?? (isset($body['id']) ? $body : null);
        return is_array($order) ? $order : null;
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
            ->connectTimeout(15)
            ->timeout(120)
            ->withOptions([
                CURLOPT_LOW_SPEED_LIMIT => 10,  // abort if transfer drops below 10 bytes/s
                CURLOPT_LOW_SPEED_TIME  => 60,  // for 60 consecutive seconds (catches stalled connections)
            ])
            ->retry(3, 5000)
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
