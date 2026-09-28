<?php

namespace App\Support;

/**
 * Projects Lagos coordinates onto the 0-100 canvas the map component draws on.
 *
 * The prototype's map was a schematic, not a chart — pins carried x/y percentages
 * straight from the fixtures. Real records carry lat/lng, so they are projected
 * here against a fixed Lagos bounding box. Fixed, not computed from the data:
 * a box that resizes itself would make the same household drift across the
 * canvas whenever a new area is added.
 */
final class Geo
{
    private const LAT_MIN = 6.42;
    private const LAT_MAX = 6.66;
    private const LNG_MIN = 3.28;
    private const LNG_MAX = 3.56;

    /** @return array{x: float, y: float} percentages, clamped to the canvas */
    public static function project(?float $lat, ?float $lng): array
    {
        // Latitude runs north-up but the canvas runs top-down, hence the flip.
        return [
            'x' => self::clamp((($lng ?? self::LNG_MIN) - self::LNG_MIN) / (self::LNG_MAX - self::LNG_MIN) * 100),
            'y' => self::clamp(100 - ((($lat ?? self::LAT_MIN) - self::LAT_MIN) / (self::LAT_MAX - self::LAT_MIN) * 100)),
        ];
    }

    /**
     * Build one pin for the map component.
     *
     * @param  'house'|'agent'|'plant'  $kind
     * @return array{x: float, y: float, kind: string, live: bool, label: ?string}
     */
    public static function pin(?float $lat, ?float $lng, string $kind = 'house', bool $live = false, ?string $label = null): array
    {
        return self::project($lat, $lng) + ['kind' => $kind, 'live' => $live, 'label' => $label];
    }

    /**
     * Whether a device location is close enough to the facility to be used.
     *
     * The browser reports wherever the phone is. Opened at a venue in Abuja, or
     * on a collector's handset with a bad fix, that is hundreds of kilometres
     * from any pickup -- and once stored, every distance on the job board and
     * every route is measured from it. Callers refuse a point outside the radius
     * and keep the position they already had.
     */
    public static function withinServiceArea(float $lat, float $lng): bool
    {
        $facility = \App\Models\Facility::first();

        $km = app(\App\Services\JobMatcher::class)->distanceKm(
            $lat, $lng,
            (float) ($facility?->lat ?? config('ecocycle.facility.lat')),
            (float) ($facility?->lng ?? config('ecocycle.facility.lng')),
        );

        return $km <= (float) config('ecocycle.service_radius_km', 80);
    }

    private static function clamp(float $v): float
    {
        return round(max(3, min(97, $v)), 2);
    }
}
