<?php

namespace App\Console\Commands;

use App\Models\LocationGeoCache;
use App\Services\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeocodeLocations extends Command
{
    protected $signature   = 'map:geocode {--level=all : province|city|all}';
    protected $description = 'Geocode all unique provinces and cities from orders into the map geocache';

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

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($rows as $row) {
            $loc = $mapper($row);

            if (LocationGeoCache::where('name_key', $loc['key'])->exists()) {
                $cached++;
                $bar->advance();
                continue;
            }

            $coords = $geo->geocodeQuery($loc['query']);

            if ($coords) {
                LocationGeoCache::updateOrCreate(['name_key' => $loc['key']], [
                    'display_name' => $loc['name'],
                    'level'        => $level,
                    'lat'          => $coords[0],
                    'lng'          => $coords[1],
                ]);
                $geocoded++;
            } else {
                $failed++;
                $this->newLine();
                $this->warn("  ✗ Not found: {$loc['query']}");
            }

            $bar->advance();
            usleep(1_100_000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("  {$total} total · {$cached} already cached · {$geocoded} geocoded · {$failed} not found");
    }
}
