<?php

namespace App\Console\Commands;

use App\Models\LocationGeoCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodeOverpass extends Command
{
    protected $signature   = 'map:geocode-overpass
                                {--level=city : province, city, or barangay}
                                {--places-only : Skip boundary-relation pass}
                                {--force : Overwrite already-cached entries}
                                {--dry-run : Show stats without writing}';
    protected $description = 'Bulk-geocode PH provinces, cities, or barangays via Overpass API (single request, no rate limits)';

    public function handle(): int
    {
        $level = $this->option('level');
        if (!in_array($level, ['province', 'city', 'barangay'])) {
            $this->error('--level must be province, city, or barangay');
            return self::FAILURE;
        }

        $osmIndex = $this->buildOsmIndex($level);
        if ($osmIndex === null) return self::FAILURE;

        $rows = $this->getPancakeLocations($level);
        $this->info("Matching " . count($rows) . " unique Pancake {$level}s to OSM...");

        $total = count($rows);
        $skipped = $matched = $ambig = $notFound = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($rows as $row) {
            $city = ($level === 'barangay') ? ($row->city ?? null) : null;
            $key  = LocationGeoCache::makeKey($row->name, $level, $row->province, $city);

            if (!$this->option('force') && LocationGeoCache::where('name_key', $key)->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $coords = $this->resolveCoords($row, $level, $osmIndex, $ambig);

            if ($coords === null) {
                $notFound++;
                // Remove stale/wrong entry so attachCoords falls back to city/province center.
                if ($this->option('force') && !$this->option('dry-run')) {
                    LocationGeoCache::where('name_key', $key)->delete();
                }
            } elseif (!$this->option('dry-run')) {
                LocationGeoCache::updateOrCreate(['name_key' => $key], [
                    'display_name' => $row->name,
                    'level'        => $level,
                    'lat'          => $coords[0],
                    'lng'          => $coords[1],
                ]);
                $matched++;
            } else {
                $matched++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done: {$total} total · {$skipped} cached · {$matched} matched · {$ambig} disambiguated · {$notFound} not in OSM");

        if ($notFound > 0) {
            $this->warn("{$notFound} {$level}s not in OSM — they will use parent-location fallback on the map.");
        }

        return self::SUCCESS;
    }

    private function buildOsmIndex(string $level): ?array
    {
        $boundaryIndex = [];

        if (!$this->option('places-only')) {
            $adminLevel = match ($level) {
                'province' => '4',
                'barangay' => '10',
                default    => '6',
            };
            $this->info("Pass 1: Fetching admin-level-{$adminLevel} boundary relations from OSM...");
            $q = '[out:json][timeout:180];'
               . 'area["ISO3166-1:alpha2"="PH"]->.ph;'
               . "relation[\"admin_level\"=\"{$adminLevel}\"][\"boundary\"=\"administrative\"](area.ph);"
               . 'out center tags;';
            $boundaryIndex = $this->queryOverpass($q, fn($el) => isset($el['center']['lat']), fn($el) => [
                'lat' => (float) $el['center']['lat'],
                'lng' => (float) $el['center']['lon'],
            ]) ?? [];
            $this->info('Boundary relations: ' . array_sum(array_map('count', $boundaryIndex)));
        } else {
            $this->info('Pass 1 skipped (--places-only).');
        }

        $placeTags = $level === 'barangay'
            ? 'village|hamlet|suburb|neighbourhood'
            : 'city|municipality|town';

        $this->info("Pass 2: Fetching place={$placeTags} nodes from OSM...");
        $q = '[out:json][timeout:150];'
           . 'area["ISO3166-1:alpha2"="PH"]->.ph;'
           . "node[\"place\"~\"^({$placeTags})\$\"](area.ph);"
           . 'out body;';
        $placeIndex = $this->queryOverpass($q, fn($el) => isset($el['lat']), fn($el) => [
            'lat' => (float) $el['lat'],
            'lng' => (float) $el['lon'],
        ]);
        if ($placeIndex === null) return null;
        $this->info('Place nodes: ' . array_sum(array_map('count', $placeIndex)));

        if ($level === 'province') {
            // For provinces, place nodes (manually placed on land by OSM editors) are more
            // reliable than boundary centroids (polygon centers that regularly fall in the sea
            // for island provinces like Palawan, Romblon, Dinagat, etc.).
            // Strategy: place node wins; boundary centroid only fills the gap when no place
            // node matches AND the centroid itself passes the Philippines bounding-box check.
            $replaced = 0;
            foreach ($boundaryIndex as $name => $entries) {
                if (isset($placeIndex[$name])) {
                    // Place node exists — it takes priority over the boundary centroid.
                    $replaced++;
                    continue;
                }
                $c = $entries[0] ?? null;
                if ($c && $this->isWithinPhilippines($c['lat'], $c['lng'])) {
                    $placeIndex[$name] = $entries; // boundary centroid looks valid, use it
                }
                // If boundary is also out of range and no place node: skip entirely so
                // attachCoords falls back to parent (better than a sea coordinate).
            }
            if ($replaced) $this->line("  {$replaced} province(s) used place-node coords over boundary centroids.");
            $this->info('Province index (place-node priority): ' . count($placeIndex) . ' entries');
            return $placeIndex;
        }

        // City / barangay: boundary relations take priority (more accurate centroid)
        $merged = $placeIndex;
        foreach ($boundaryIndex as $name => $entries) {
            $merged[$name] = $entries;
        }
        $this->info('Combined OSM index: ' . array_sum(array_map('count', $merged)) . ' unique names');

        return $merged;
    }

    private function getPancakeLocations(string $level): array
    {
        return match ($level) {
            'province' => DB::select(
                'SELECT DISTINCT province as name, NULL as province, NULL as city
                 FROM orders WHERE province IS NOT NULL AND province != ""
                 ORDER BY province'
            ),
            'barangay' => DB::select(
                'SELECT ward as name, province, district as city
                 FROM orders WHERE ward IS NOT NULL AND ward != "" AND province IS NOT NULL
                   AND district IS NOT NULL AND district != ""
                 GROUP BY ward, province, district ORDER BY province, district, ward'
            ),
            default => DB::select(
                'SELECT DISTINCT district as name, MAX(province) as province, NULL as city
                 FROM orders WHERE district IS NOT NULL AND district != ""
                 GROUP BY district ORDER BY district'
            ),
        };
    }

    private function resolveCoords(object $row, string $level, array $osmIndex, int &$ambig): ?array
    {
        $province = LocationGeoCache::normalize($row->province ?? '');
        $rawName  = LocationGeoCache::normalize($row->name);

        if ($level === 'province') {
            $candidates = $osmIndex[$rawName] ?? [];
        } elseif ($level === 'city') {
            // Strip province prefix only when meaningful: "abra dolores" → "dolores" ✓
            //                                             "cebu city" → "city" ✗ (skip)
            if ($province && str_starts_with($rawName, $province . ' ')) {
                $stripped = ltrim(substr($rawName, strlen($province)), ' ');
                if (strlen($stripped) > 4 && $stripped !== 'city') {
                    $rawName = $stripped;
                }
            }
            // Also try without " city" suffix (e.g. "angeles city" → "angeles")
            $candidates = $osmIndex[$rawName]
                       ?? $osmIndex[LocationGeoCache::stripCitySuffix($rawName)]
                       ?? [];
        } else {
            // Barangay: strip parentheticals like "(pob.)" and try plain name
            $plain      = trim(preg_replace('/\s*\(.*?\)/', '', $rawName));
            $candidates = $osmIndex[$rawName] ?? ($plain !== $rawName ? ($osmIndex[$plain] ?? []) : []);
        }

        if (empty($candidates)) return null;

        // Build anchor first — needed for single-candidate barangay validation too.
        $anchor  = null;
        $maxDist = 3.0;

        if ($level === 'barangay' && !empty($row->city)) {
            $cityNorm2   = LocationGeoCache::normalize($row->city);
            $cityNoSufx2 = LocationGeoCache::stripCitySuffix($cityNorm2);
            $provNorm2   = LocationGeoCache::normalize($row->province ?? '');
            $anchor      = LocationGeoCache::whereIn('name_key', array_filter([
                'city:' . $cityNorm2 . ($provNorm2 ? ':' . $provNorm2 : ''),
                'city:' . $cityNoSufx2 . ($provNorm2 ? ':' . $provNorm2 : ''),
                'city:' . $cityNorm2,
                'city:' . $cityNoSufx2,
            ]))->first();
            if ($anchor) $maxDist = 1.0;
        }
        if (!$anchor) {
            $provKey = LocationGeoCache::makeKey($row->province ?? '', 'province');
            $anchor  = LocationGeoCache::where('name_key', $provKey)->first();
        }

        if (count($candidates) === 1) {
            $c = $candidates[0];
            if (!$this->isWithinPhilippines($c['lat'], $c['lng'])) return null;
            // For barangays with a city anchor, apply a tighter 0.3° radius (~33 km) on the
            // single-candidate path to reject same-named barangays from neighbouring municipalities.
            // (The multi-candidate path uses 1.0° because it must choose the closest of several hits.)
            if ($level === 'barangay' && $anchor) {
                if ($this->manhattanDist($c['lat'], $c['lng'], $anchor->lat, $anchor->lng) > 0.3) return null;
            }
            return [$c['lat'], $c['lng']];
        }

        // Ambiguous (multiple candidates) — pick closest to anchor.
        $ambig++;

        if (!$anchor) return [$candidates[0]['lat'], $candidates[0]['lng']];

        $closest = null;
        $minDist = PHP_INT_MAX;
        foreach ($candidates as $c) {
            $dist = $this->manhattanDist($c['lat'], $c['lng'], $anchor->lat, $anchor->lng);
            if ($dist < $minDist) { $minDist = $dist; $closest = $c; }
        }

        // For barangays with a city anchor, use the same 0.3° tight cap as the single-candidate
        // path — the closest of several wrong-municipality hits still loses to city-jitter fallback.
        $effectiveMax = ($level === 'barangay' && $anchor) ? 0.3 : $maxDist;
        if ($minDist > $effectiveMax) return null;

        return $this->isWithinPhilippines($closest['lat'], $closest['lng'])
            ? [$closest['lat'], $closest['lng']]
            : null;
    }

    private function manhattanDist(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return abs($lat1 - $lat2) + abs($lng1 - $lng2);
    }

    /**
     * Rough bounding box guard for the Philippine archipelago.
     * Rejects results that Overpass returned but clearly fall outside PH territory
     * (e.g. admin boundary centroids that land in the open sea far from any island).
     */
    private function isWithinPhilippines(float $lat, float $lng): bool
    {
        // Tight west bound: Palawan's coast is ~117.2°E at Balabac; anything further west
        // is open South China Sea and should not be stored as a PH location.
        return $lat >= 4.5 && $lat <= 21.5 && $lng >= 117.0 && $lng <= 128.5;
    }

    private function queryOverpass(string $query, callable $hasCoords, callable $extractCoords): ?array
    {
        $endpoints = [
            'https://overpass-api.de/api/interpreter',
            'https://lz4.overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
        ];

        $response = null;
        foreach ($endpoints as $url) {
            try {
                $r = Http::timeout(200)
                    ->withHeaders(['User-Agent' => 'PancakeAnalytics/1.0 (ahlyssar.work@gmail.com)'])
                    ->get($url, ['data' => $query]);
                if ($r->ok()) { $response = $r; break; }
                $this->warn("  {$url} → HTTP {$r->status()}, trying next...");
            } catch (\Throwable $e) {
                $this->warn("  {$url} → " . $e->getMessage() . ', trying next...');
            }
        }

        if (!$response) {
            $this->error('All Overpass endpoints failed.');
            return null;
        }

        $elements = $response->json()['elements'] ?? [];
        $index    = [];
        foreach ($elements as $el) {
            $name = $el['tags']['name'] ?? ($el['tags']['name:en'] ?? null);
            if (!$name || !$hasCoords($el)) continue;
            $index[LocationGeoCache::normalize($name)][] = $extractCoords($el) + ['name' => $name];
        }

        return $index;
    }
}
