<?php

namespace App\Feature\TrainCheckin\DTO;

use App\Http\Resources\PointsCalculationResource;
use App\Models\Status;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrainCheckinResult
{
    private Status $status;
    private PointsCalculationResource $pointsCalculationResource;
    private AnonymousResourceCollection $alsoOnThisConnection;

    public function __construct(Status $status, PointsCalculationResource $pointsCalculationResource, AnonymousResourceCollection $alsoOnThisConnection) {
        $this->status                    = $status;
        $this->pointsCalculationResource = $pointsCalculationResource;
        $this->alsoOnThisConnection      = $alsoOnThisConnection;
    }

    public function getStatus(): Status {
        return $this->status;
    }

    public function getPointsCalculationResource(): PointsCalculationResource {
        return $this->pointsCalculationResource;
    }

    public function getAlsoOnThisConnection(): AnonymousResourceCollection {
        return $this->alsoOnThisConnection;
    }
}
