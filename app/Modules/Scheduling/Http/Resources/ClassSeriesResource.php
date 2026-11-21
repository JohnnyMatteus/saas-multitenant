<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\Models\ClassSeries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ClassSeries $resource
 */
final class ClassSeriesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $series = $this->resource;

        return [
            'id' => $series->id,
            'title' => $series->title,
            'instructor_id' => $series->instructor_id,
            'weekdays' => $series->weekdays,
            'start_time' => $series->start_time,
            'duration_minutes' => $series->duration_minutes,
            'capacity' => $series->capacity,
            'starts_on' => $series->starts_on->toDateString(),
            'ends_on' => $series->ends_on->toDateString(),
            'rolling' => $series->rolling,
            'sessions_count' => $series->getAttribute('sessions_count'),
        ];
    }
}
