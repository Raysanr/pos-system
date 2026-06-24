<?php

namespace Tests\Unit;

use App\Models\LocationGeoCache;
use Tests\TestCase;

class LocationGeoCacheTest extends TestCase
{
    public function test_strip_city_suffix_removes_trailing_city(): void
    {
        $this->assertSame('angeles', LocationGeoCache::stripCitySuffix('angeles city'));
        $this->assertSame('quezon', LocationGeoCache::stripCitySuffix('quezon city'));
    }

    public function test_strip_city_suffix_is_noop_when_no_suffix(): void
    {
        $this->assertSame('cebu', LocationGeoCache::stripCitySuffix('cebu'));
        $this->assertSame('manila', LocationGeoCache::stripCitySuffix('manila'));
    }

    public function test_strip_city_suffix_does_not_strip_mid_word_city(): void
    {
        // "city" in the middle must not be stripped
        $this->assertSame('city of san jose', LocationGeoCache::stripCitySuffix('city of san jose'));
    }

    public function test_normalize_lowercases_and_collapses_whitespace(): void
    {
        $this->assertSame('metro manila', LocationGeoCache::normalize('Metro  Manila'));
        $this->assertSame('metro manila', LocationGeoCache::normalize('Metro-Manila'));
    }

    public function test_make_key_formats_province_level(): void
    {
        $key = LocationGeoCache::makeKey('Cebu', 'province');
        $this->assertSame('province:cebu', $key);
    }

    public function test_make_key_appends_city_and_province(): void
    {
        $key = LocationGeoCache::makeKey('Lahug', 'barangay', 'Cebu', 'Cebu City');
        $this->assertSame('barangay:lahug:cebu city:cebu', $key);
    }
}
