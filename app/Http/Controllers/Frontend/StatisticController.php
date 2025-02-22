<?php

namespace App\Http\Controllers\Frontend;

use App\Enum\Business;
use App\Http\Controllers\Backend\StatisticController as StatisticBackend;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticController extends Controller
{
    public function renderMainStats(Request $request): View {
        $validated = $request->validate([
                                            'from' => ['nullable', 'date'],
                                            'to'   => ['nullable', 'date', 'after_or_equal:from']
                                        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : Carbon::now()->subQuarter();
        $to   = isset($validated['to']) ? Carbon::parse($validated['to']) : Carbon::now();

        $globalStats = StatisticBackend::getGlobalCheckInStats($from, $to);

        $topCategories  = StatisticBackend::getTopTravelCategoryByUser(auth()->user(), $from, $to);
        $topOperators   = StatisticBackend::getTopTripOperatorByUser(auth()->user(), $from, $to);
        $travelTime     = StatisticBackend::getDailyTravelTimeByUser(auth()->user(), $from, $to);
        $travelPurposes = StatisticBackend::getTravelPurposes(auth()->user(), $from, $to);

        $travelPurposes = $travelPurposes->map(function($row) {
            if ($row->reason === Business::PRIVATE->value) {
                $row->reason = __('stationboard.business.private');
            } elseif ($row->reason === Business::BUSINESS->value) {
                $row->reason = __('stationboard.business.business');
            } elseif ($row->reason === Business::COMMUTE->value) {
                $row->reason = __('stationboard.business.commute');
            }
            return $row;
        });

        $topCategories = $topCategories->map(function($row) {
            $row->name = __('transport_types.' . $row->name);
            return $row;
        });

        return view('stats.stats', [
            'from'           => $from,
            'to'             => $to,
            'globalStats'    => $globalStats,
            'topCategories'  => $topCategories,
            'topOperators'   => $topOperators,
            'travelTime'     => $travelTime,
            'travelPurposes' => $travelPurposes,
        ]);
    }

    public function renderStations(Request $request): View {
        $validated = $request->validate([
                                            'from' => ['nullable', 'date'],
                                            'to'   => ['nullable', 'date', 'after_or_equal:from']
                                        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : Carbon::now()->subQuarter()->startOfDay();
        $to   = isset($validated['to']) ? Carbon::parse($validated['to']) : Carbon::now()->endOfDay();

        $usedStations   = StatisticBackend::getUsedStations(auth()->user(), $from, $to);
        $passedStations = StatisticBackend::getPassedStations(auth()->user(), $from, $to)
                                          ->reject(function($station) use ($usedStations) {
                                              return $usedStations->contains('id', $station->id);
                                          });

        return view('stats.stations', [
            'usedStations'   => $usedStations,
            'passedStations' => $passedStations,
        ]);

    }
}
