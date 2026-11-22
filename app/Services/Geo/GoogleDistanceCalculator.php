<?php

namespace App\Services\Geo;

use App\Contracts\DistanceCalculator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driving distance from the Google Distance Matrix API, cached per rounded
 * coordinate pair. Falls back to straight-line distance on any failure so
 * checkout never breaks because of the maps provider.
 */
class GoogleDistanceCalculator implements DistanceCalculator
{
    public function __construct(
        private readonly string $apiKey,
        private readonly HaversineDistanceCalculator $fallback,
    ) {}

    public function kilometers(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $origin = sprintf('%.5f,%.5f', $fromLat, $fromLng);
        $destination = sprintf('%.5f,%.5f', $toLat, $toLng);

        return Cache::remember(
            "distance:{$origin}:{$destination}",
            now()->addDay(),
            function () use ($origin, $destination, $fromLat, $fromLng, $toLat, $toLng): float {
                try {
                    $response = Http::timeout(5)
                        ->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                            'origins' => $origin,
                            'destinations' => $destination,
                            'mode' => 'driving',
                            'units' => 'metric',
                            'key' => $this->apiKey,
                        ]);

                    $element = $response->json('rows.0.elements.0');

                    if ($response->successful() && ($element['status'] ?? null) === 'OK') {
                        return ((int) $element['distance']['value']) / 1000;
                    }

                    Log::warning('Distance Matrix returned no route.', [
                        'status' => $response->json('status'),
                        'element_status' => $element['status'] ?? null,
                    ]);
                } catch (\Throwable $exception) {
                    Log::warning('Distance Matrix request failed.', ['message' => $exception->getMessage()]);
                }

                return $this->fallback->kilometers($fromLat, $fromLng, $toLat, $toLng);
            },
        );
    }
}
