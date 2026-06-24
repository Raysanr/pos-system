<?php

namespace App\Services;

use App\Models\LocationGeoCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    // SQLite SQLITE_MAX_VARIABLE_NUMBER is 32766; stay well under it.
    private const GEO_CHUNK_SIZE = 900;

    /**
     * Attach lat/lng to each location item from the geocache.
     * For barangay level, falls back to parent city → parent province coords
     * so every item gets a position without any live geocoding on page load.
     */
    public function attachCoords(array $items, string $level, ?string $province): array
    {
        if (empty($items)) return $items;

        // Build all candidate keys per item. Barangay level adds city-specific keys
        // (highest priority) to avoid same-name barangays in different municipalities
        // pointing to the wrong location.
        $allKeys = [];
        foreach ($items as $item) {
            $ownProv    = $item['province'] ?? null;
            $ownCity    = $item['city']     ?? null;
            $norm       = LocationGeoCache::normalize($item['name']);
            $normNoCity = LocationGeoCache::stripCitySuffix($norm);

            if ($level === 'barangay') {
                // City+province specific keys take priority over province-only keys.
                $allKeys[] = LocationGeoCache::makeKey($item['name'], $level, $ownProv, $ownCity);
                $allKeys[] = LocationGeoCache::makeKey($item['name'], $level, $province, $ownCity);
                if ($norm !== $normNoCity) {
                    $allKeys[] = LocationGeoCache::makeKey($normNoCity, $level, $ownProv, $ownCity);
                    $allKeys[] = LocationGeoCache::makeKey($normNoCity, $level, $province, $ownCity);
                }
                // Pre-build fallback city keys so a second fetchGeoCache is not needed.
                if (!empty($ownCity)) {
                    $allKeys[] = LocationGeoCache::makeKey($ownCity, 'city', $ownProv);
                    $allKeys[] = LocationGeoCache::makeKey($ownCity, 'city', null);
                }
            }

            $allKeys[]  = LocationGeoCache::makeKey($item['name'], $level, $province);
            $allKeys[]  = LocationGeoCache::makeKey($item['name'], $level, $ownProv);
            $allKeys[]  = LocationGeoCache::makeKey($item['name'], $level, null);
            if ($norm !== $normNoCity) {
                $allKeys[] = LocationGeoCache::makeKey($normNoCity, $level, $province);
                $allKeys[] = LocationGeoCache::makeKey($normNoCity, $level, $ownProv);
                $allKeys[] = LocationGeoCache::makeKey($normNoCity, $level, null);
            }

            // Pre-build fallback province key.
            if (!empty($ownProv)) {
                $allKeys[] = LocationGeoCache::makeKey($ownProv, 'province', null);
            }
        }
        $cached = $this->fetchGeoCache(array_unique($allKeys));

        foreach ($items as &$item) {
            $ownProv    = $item['province'] ?? null;
            $ownCity    = $item['city']     ?? null;
            $norm       = LocationGeoCache::normalize($item['name']);
            $normNoCity = LocationGeoCache::stripCitySuffix($norm);

            // For barangays, city+province specific key takes priority over province-only
            // to correctly distinguish same-name barangays in different municipalities.
            $ref = null;
            if ($level === 'barangay') {
                $ref = $cached->get(LocationGeoCache::makeKey($item['name'], $level, $ownProv, $ownCity))
                    ?? $cached->get(LocationGeoCache::makeKey($item['name'], $level, $province, $ownCity))
                    ?? ($norm !== $normNoCity ? $cached->get(LocationGeoCache::makeKey($normNoCity, $level, $ownProv, $ownCity)) : null)
                    ?? ($norm !== $normNoCity ? $cached->get(LocationGeoCache::makeKey($normNoCity, $level, $province, $ownCity)) : null);
            }

            // For barangay level, skip province-only key fallback — old province-only geocache
            // entries can point to wrong municipalities (same barangay name, different city).
            // Those items fall through to city-center jitter, which is more accurate.
            if ($level !== 'barangay') {
                $ref ??= $cached->get(LocationGeoCache::makeKey($item['name'], $level, $province))
                    ?? $cached->get(LocationGeoCache::makeKey($item['name'], $level, $ownProv))
                    ?? $cached->get(LocationGeoCache::makeKey($item['name'], $level, null))
                    ?? ($norm !== $normNoCity ? $cached->get(LocationGeoCache::makeKey($normNoCity, $level, $province)) : null)
                    ?? ($norm !== $normNoCity ? $cached->get(LocationGeoCache::makeKey($normNoCity, $level, $ownProv)) : null)
                    ?? ($norm !== $normNoCity ? $cached->get(LocationGeoCache::makeKey($normNoCity, $level, null)) : null);
            }

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
        // Fallback keys were pre-built in the initial loop, so $cached already has them.
        foreach ($items as &$item) {
            if ($item['lat'] !== null) continue;

            $ref = null;
            if ($level === 'barangay' && !empty($item['city'])) {
                $ref = $cached->get(LocationGeoCache::makeKey($item['city'], 'city', $item['province'] ?? null))
                    ?? $cached->get(LocationGeoCache::makeKey($item['city'], 'city', null));
            }
            if (!$ref && !empty($item['province'])) {
                $ref = $cached->get(LocationGeoCache::makeKey($item['province'], 'province', null));
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

        return $items;
    }

    private function fetchGeoCache(array $keys): \Illuminate\Support\Collection
    {
        $result = collect();
        foreach (array_chunk(array_values($keys), self::GEO_CHUNK_SIZE) as $chunk) {
            $result = $result->merge(LocationGeoCache::whereIn('name_key', $chunk)->get());
        }
        return $result->keyBy('name_key');
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
