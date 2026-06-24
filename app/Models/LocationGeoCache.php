<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationGeoCache extends Model
{
    protected $table = 'location_geocache';

    protected $fillable = ['name_key', 'display_name', 'level', 'lat', 'lng'];

    public static function normalize(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace('-', ' ', strtolower($name))));
    }

    public static function stripCitySuffix(string $norm): string
    {
        return preg_replace('/\s+city$/', '', $norm);
    }

    public static function makeKey(string $name, string $level, ?string $province = null, ?string $city = null): string
    {
        $key = $level . ':' . self::normalize($name);
        if ($city) {
            $key .= ':' . self::normalize($city);
        }
        if ($province) {
            $key .= ':' . self::normalize($province);
        }
        return $key;
    }
}
