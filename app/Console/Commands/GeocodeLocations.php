<?php

namespace App\Console\Commands;

use App\Models\LocationGeoCache;
use App\Services\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeocodeLocations extends Command
{
    protected $signature   = 'map:geocode {--level=all : province|city|all} {--force : Overwrite already-cached entries}';
    protected $description = 'Geocode all unique provinces and cities from orders into the map geocache (Nominatim, province-context-aware)';

    public function handle(GeocodingService $geo): int
    {
        $level = $this->option('level');

        if (in_array($level, ['province', 'all'])) $this->geocodeProvinces($geo);
        if (in_array($level, ['city',     'all'])) $this->geocodeCities($geo);

        $this->info('Done. Barangay circles use parent city coordinates automatically.');
        return self::SUCCESS;
    }

    private function geocodeProvinces(GeocodingService $geo): void
    {
        $this->info('── provinces ──');

        $rows = DB::select(
            'SELECT DISTINCT province as name FROM orders WHERE province IS NOT NULL AND province != "" ORDER BY province'
        );

        $this->runGeocode($rows, 'province', fn($row) => [
            'name'     => $row->name,
            'city'     => null,
            'province' => null,
            'key'      => LocationGeoCache::makeKey($row->name, 'province'),
            'query'    => LocationGeoCache::normalize($row->name) . ', Philippines',
        ], $geo);
    }

    private function geocodeCities(GeocodingService $geo): void
    {
        $this->info('── cities / municipalities ──');

        $rows = DB::select(
            'SELECT DISTINCT district as name, MAX(province) as province FROM orders
             WHERE district IS NOT NULL AND district != "" GROUP BY district ORDER BY district'
        );

        $this->runGeocode($rows, 'city', function ($row) {
            $province = LocationGeoCache::normalize($row->province ?? '');
            $rawCity  = LocationGeoCache::normalize($row->name);

            // Strip province prefix: "abra pilar" with province "abra" → "pilar"
            if ($province && str_starts_with($rawCity, $province . ' ')) {
                $rawCity = ltrim(substr($rawCity, strlen($province)), ' ');
            }

            return [
                'name'     => $row->name,
                'city'     => null,
                'province' => $row->province,
                'key'      => LocationGeoCache::makeKey($row->name, 'city', $row->province),
                'query'    => $rawCity . ($province ? ", {$province}" : '') . ', Philippines',
            ];
        }, $geo);
    }

    private function runGeocode(array $rows, string $level, callable $mapper, GeocodingService $geo): void
    {
        $total    = count($rows);
        $cached   = 0;
        $geocoded = 0;
        $failed   = 0;
        $rejected = 0;

        // Pre-load province centers for cross-province sanity check
        $provinceCenters = LocationGeoCache::where('level', 'province')
            ->get(['name_key', 'lat', 'lng'])
            ->keyBy('name_key');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($rows as $row) {
            $loc = $mapper($row);

            if (!$this->option('force') && LocationGeoCache::where('name_key', $loc['key'])->exists()) {
                $cached++;
                $bar->advance();
                continue;
            }

            $coords = $geo->geocodeQuery($loc['query']);

            if ($coords) {
                // For city-level, reject results that are more than 2° from the
                // province center — this catches wrong-province matches for common
                // names like "San Fernando" or "San Jose".
                if ($level === 'city' && !empty($loc['province'])) {
                    $provKey    = LocationGeoCache::makeKey($loc['province'], 'province');
                    $provAnchor = $provinceCenters->get($provKey);
                    if ($provAnchor) {
                        $latDiff = abs($coords[0] - $provAnchor->lat);
                        $lngDiff = abs($coords[1] - $provAnchor->lng);
                        // Per-dimension check: each coordinate must be within 1° of the
                        // province center. This is tighter than a Manhattan-distance check
                        // and correctly rejects wrong-province Nominatim hits for common
                        // names (e.g. "san jose, camarines sur" returning Batangas coords).
                        if ($latDiff > 1.0 || $lngDiff > 1.0) {
                            $rejected++;
                            $bar->advance();
                            usleep(1_100_000);
                            continue; // Skip — falls back to province-center jitter on the map
                        }
                    }
                }

                LocationGeoCache::updateOrCreate(['name_key' => $loc['key']], [
                    'display_name' => $loc['name'],
                    'level'        => $level,
                    'lat'          => $coords[0],
                    'lng'          => $coords[1],
                ]);
                $geocoded++;
            } else {
                $failed++;
            }

            $bar->advance();
            usleep(1_100_000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("  {$total} total · {$cached} cached · {$geocoded} geocoded · {$rejected} rejected (wrong province) · {$failed} not found");
    }
}
