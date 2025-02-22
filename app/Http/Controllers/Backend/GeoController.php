<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\HafasTrip;
use App\Models\TrainCheckin;
use App\Models\TrainStopover;
use Exception;
use JsonException;
use stdClass;

abstract class GeoController extends Controller
{

    public static function calculateDistance(
        HafasTrip     $hafasTrip,
        TrainStopover $origin,
        TrainStopover $destination
    ): int {
        if ($hafasTrip->polyline === null || $hafasTrip?->polyline?->polyline === null) {
            return self::calculateDistanceByStopovers($hafasTrip, $origin, $destination);
        }
        $geoJson      = self::getPolylineBetween($hafasTrip, $origin, $destination);
        $distance     = 0;
        $lastStopover = null;
        foreach ($geoJson->features as $stopover) {
            if ($lastStopover !== null) {
                $distance += self::calculateDistanceBetweenCoordinates(
                    latitudeA:  $lastStopover->geometry->coordinates[1],
                    longitudeA: $lastStopover->geometry->coordinates[0],
                    latitudeB:  $stopover->geometry->coordinates[1],
                    longitudeB: $stopover->geometry->coordinates[0]
                );
            }

            $lastStopover = $stopover;
        }
        return $distance;
    }

    /**
     * Fallback calculation if no polyline is given. Calculates the length using the coordinates of the stations.
     *
     * @param HafasTrip     $hafasTrip
     * @param TrainStopover $origin
     * @param TrainStopover $destination
     *
     * @return int
     */
    private static function calculateDistanceByStopovers(
        HafasTrip     $hafasTrip,
        TrainStopover $origin,
        TrainStopover $destination
    ): int {
        $stopovers                = $hafasTrip->stopoversNEW->sortBy('departure');
        $originStopoverIndex      = $stopovers->search(function($item) use ($origin) {
            return $item->is($origin);
        });
        $destinationStopoverIndex = $stopovers->search(function($item) use ($destination) {
            return $item->is($destination);
        });

        $stopovers = $stopovers->slice($originStopoverIndex, $destinationStopoverIndex - $originStopoverIndex + 1);

        $distance     = 0;
        $lastStopover = null;
        foreach ($stopovers as $stopover) {
            if ($lastStopover === null) {
                $lastStopover = $stopover;
                continue;
            }
            $distance     += self::calculateDistanceBetweenCoordinates(
                latitudeA:  $lastStopover->trainStation->latitude,
                longitudeA: $lastStopover->trainStation->longitude,
                latitudeB:  $stopover->trainStation->latitude,
                longitudeB: $stopover->trainStation->longitude
            );
            $lastStopover = $stopover;
        }
        return $distance;
    }

    public static function calculateDistanceBetweenCoordinates(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB
    ): int {
        if ($longitudeA === $longitudeB && $latitudeA === $latitudeB) {
            return 0.0;
        }

        $equatorialRadiusInMeters = 6378137;

        $latA     = $latitudeA / 180 * M_PI;
        $lonA     = $longitudeA / 180 * M_PI;
        $latB     = $latitudeB / 180 * M_PI;
        $lonB     = $longitudeB / 180 * M_PI;
        $distance = acos(sin($latA) * sin($latB) + cos($latA) * cos($latB) * cos($lonB - $lonA))
                    * $equatorialRadiusInMeters;

        return round($distance);
    }

    /**
     * @throws JsonException
     */
    private static function getPolylineBetween(HafasTrip $hafasTrip, TrainStopover $origin, TrainStopover $destination) {
        $geoJson = self::getPolylineWithTimestamps($hafasTrip);

        $originIndex      = null;
        $destinationIndex = null;
        foreach ($geoJson->features as $key => $data) {
            if (!isset($data->properties->id)) {
                continue;
            }
            if ($originIndex === null
                && $origin->trainStation->ibnr === (int) $data->properties->id
                && isset($data->properties->departure_planned) //Important for ring lines!
                && $origin->departure_planned->is($data->properties->departure_planned) //Important for ring lines!
            ) {
                $originIndex = $key;
            }

            if ($destinationIndex === null
                && $destination->trainStation->ibnr === (int) $data->properties->id
                && isset($data->properties->arrival_planned) //Important for ring lines!
                && $destination->arrival_planned->is($data->properties->arrival_planned) //Important for ring lines!
            ) {
                $destinationIndex = $key;
            }
        }

        $slicedFeatures    = array_slice($geoJson->features, $originIndex, $destinationIndex - $originIndex + 1);
        $geoJson->features = $slicedFeatures;
        return $geoJson;
    }

    /**
     * Timestamps in the GeoJSON are required to calculate the distance of ring lines correctly.
     *
     * @param HafasTrip $hafasTrip
     *
     * @return mixed
     * @throws JsonException
     */
    private static function getPolylineWithTimestamps(HafasTrip $hafasTrip): stdClass {
        $geoJsonObj = json_decode($hafasTrip->polyline->polyline, false, 512, JSON_THROW_ON_ERROR);
        $stopovers  = $hafasTrip->stopoversNEW;

        $stopovers = $stopovers->map(function($stopover) {
            $stopover['passed'] = false;
            return $stopover;
        });

        foreach ($geoJsonObj->features as $polylineFeature) {
            if (!isset($polylineFeature->properties->id)) {
                continue;
            }

            $stopover = $stopovers->where('trainStation.ibnr', $polylineFeature->properties->id)
                                  ->where('passed', false)
                                  ->first();

            if (is_null($stopover)) {
                continue;
            }

            $stopover->passed                               = true;
            $polylineFeature->properties->departure_planned = $stopover->departure_planned?->clone();
            $polylineFeature->properties->arrival_planned   = $stopover->arrival_planned?->clone();
        }
        return $geoJsonObj;
    }

    public static function getMapLinesForCheckin(TrainCheckin $checkin): array {
        try {
            $geoJson  = self::getPolylineBetween($checkin->hafasTrip, $checkin->origin_stopover, $checkin->destination_stopover);
            $mapLines = [];
            foreach ($geoJson->features as $feature) {
                if (isset($feature->geometry->coordinates[0], $feature->geometry->coordinates[1])) {
                    $mapLines[] = [
                        $feature->geometry->coordinates[0],
                        $feature->geometry->coordinates[1]
                    ];
                }
            }
            return $mapLines;
        } catch (Exception $exception) {
            report($exception);
            return [
                [$checkin->originStation->latitude, $checkin->originStation->longitude],
                [$checkin->destinationStation->latitude, $checkin->destinationStation->longitude]
            ];
        }
    }
}
