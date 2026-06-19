<?php

namespace App\Services;

use App\Models\LocationGeoCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Attach lat/lng to each location item from the geocache.
     * For barangay level, falls back to parent city → parent province coords
     * so every item gets a position without any live geocoding on page load.
     */
    public function attachCoords(array $items, string $level, ?string $province): array
    {
        if (empty($items)) return $items;

        // Build all fallback keys for each item so we can batch-fetch in one query.
        // Priority: (1) filter province, (2) item's own province, (3) no province,
        //           (4-6) same three with " city" suffix stripped from name.
        $allKeys = [];
        foreach ($items as $item) {
            $ownProv    = $item['province'] ?? null;
            $norm       = LocationGeoCache::normalize($item['name']);
            $normNoCity = preg_replace('/\s+city$/', '', $norm);
            $allKeys[]  = LocationGeoCache::makeKey($item['name'], $level, $province);
            $allKeys[]  = LocationGeoCache::makeKey($item['name'], $level, $ownProv);
            $allKeys[]  = LocationGeoCache::makeKey($item['name'], $level, null);
            if ($norm !== $normNoCity) {
                $allKeys[] = LocationGeoCache::makeKey($normNoCity, $level, $province);
                $allKeys[] = LocationGeoCache::makeKey($normNoCity, $level, $ownProv);
                $allKeys[] = LocationGeoCache::makeKey($normNoCity, $level, null);
            }
        }
        $cached = LocationGeoCache::whereIn('name_key', array_unique($allKeys))->get()->keyBy('name_key');

        foreach ($items as &$item) {
            $ownProv    = $item['province'] ?? null;
            $norm       = LocationGeoCache::normalize($item['name']);
            $normNoCity = preg_replace('/\s+city$/', '', $norm);

            $ref = $cached->get(LocationGeoCache::makeKey($item['name'], $level, $province))
                ?? $cached->get(LocationGeoCache::makeKey($item['name'], $level, $ownProv))
                ?? $cached->get(LocationGeoCache::makeKey($item['name'], $level, null))
                ?? ($norm !== $normNoCity ? $cached->get(LocationGeoCache::makeKey($normNoCity, $level, $province)) : null)
                ?? ($norm !== $normNoCity ? $cached->get(LocationGeoCache::makeKey($normNoCity, $level, $ownProv)) : null)
                ?? ($norm !== $normNoCity ? $cached->get(LocationGeoCache::makeKey($normNoCity, $level, null)) : null);

            if ($ref) {
                $item['lat'] = (float) $ref->lat;
                $item['lng'] = (float) $ref->lng;
            } else {
                $item['lat'] = null;
                $item['lng'] = null;
            }
        }
        unset($item);

        // Fallback: use province center for items still missing coordinates.
        // City level gets ±0.03° jitter; barangay level also tries parent city first.
        $stillMissing = array_filter($items, fn($d) => $d['lat'] === null);
        if (!empty($stillMissing)) {
            $fallbackKeys = [];

            if ($level === 'barangay') {
                foreach ($stillMissing as $d) {
                    if (!empty($d['city'])) {
                        $fallbackKeys[] = LocationGeoCache::makeKey($d['city'], 'city', null);
                    }
                }
            }
            foreach ($stillMissing as $d) {
                if (!empty($d['province'])) {
                    $fallbackKeys[] = LocationGeoCache::makeKey($d['province'], 'province', null);
                }
            }

            $fallback = LocationGeoCache::whereIn('name_key', array_unique($fallbackKeys))
                            ->get()->keyBy('name_key');

            foreach ($items as &$item) {
                if ($item['lat'] !== null) continue;

                $ref = null;
                if ($level === 'barangay' && !empty($item['city'])) {
                    $ref = $fallback->get(LocationGeoCache::makeKey($item['city'], 'city', null));
                }
                if (!$ref && !empty($item['province'])) {
                    $ref = $fallback->get(LocationGeoCache::makeKey($item['province'], 'province', null));
                }

                if ($ref) {
                    // Jitter so items in the same parent don't stack (barangay ±0.05°, city ±0.03°)
                    $jitter = $level === 'barangay' ? 50 : 30;
                    $item['lat']    = (float) $ref->lat + (rand(-$jitter, $jitter) / 1000);
                    $item['lng']    = (float) $ref->lng + (rand(-$jitter, $jitter) / 1000);
                    $item['approx'] = true;
                }
            }
            unset($item);
        }

        return $items;
    }

    /**
     * Geocode a pre-built query string via Nominatim.
     * Used by the `map:geocode` artisan command.
     */
    public function geocodeQuery(string $query): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'PancakeAnalytics/1.0 (ahlyssar.work@gmail.com)'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q'            => $query,
                    'format'       => 'json',
                    'limit'        => 1,
                    'countrycodes' => 'ph',
                ]);

            $results = $response->json();
            if (!empty($results[0]['lat'])) {
                return [(float) $results[0]['lat'], (float) $results[0]['lon']];
            }
        } catch (\Throwable $e) {
            Log::warning("Geocoding failed for '{$query}': " . $e->getMessage());
        }

        return null;
    }
}
