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
            $key = LocationGeoCache::makeKey($row->name, $level, $row->province);

            if (!$this->option('force') && LocationGeoCache::where('name_key', $key)->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $coords = $this->resolveCoords($row, $level, $osmIndex, $ambig);

            if ($coords === null) {
                $notFound++;
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

        // Provinces don't benefit from place nodes — boundary centroids are sufficient
        if ($level === 'province') {
            return $boundaryIndex;
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

        // Boundary relations take priority (more accurate centroid)
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
                'SELECT DISTINCT ward as name, MAX(province) as province, MAX(district) as city
                 FROM orders WHERE ward IS NOT NULL AND ward != ""
                 GROUP BY ward ORDER BY ward'
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
                       ?? $osmIndex[preg_replace('/\s+city$/', '', $rawName)]
                       ?? [];
        } else {
            // Barangay: strip parentheticals like "(pob.)" and try plain name
            $plain      = trim(preg_replace('/\s*\(.*?\)/', '', $rawName));
            $candidates = $osmIndex[$rawName] ?? ($plain !== $rawName ? ($osmIndex[$plain] ?? []) : []);
        }

        if (empty($candidates)) return null;
        if (count($candidates) === 1) return [$candidates[0]['lat'], $candidates[0]['lng']];

        // Ambiguous — pick closest to province center
        $ambig++;
        $provKey   = LocationGeoCache::makeKey($row->province ?? '', 'province');
        $provCache = LocationGeoCache::where('name_key', $provKey)->first();

        if (!$provCache) return [$candidates[0]['lat'], $candidates[0]['lng']];

        $closest = null;
        $minDist = PHP_INT_MAX;
        foreach ($candidates as $c) {
            $dist = abs($c['lat'] - $provCache->lat) + abs($c['lng'] - $provCache->lng);
            if ($dist < $minDist) { $minDist = $dist; $closest = $c; }
        }

        return [$closest['lat'], $closest['lng']];
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
