<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Scheduling\Application\CreateClassSeriesData;
use App\Modules\Scheduling\Domain\WeeklyRecurrence;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de formato. Regras de negócio (período máximo, instrutor do
 * tenant) ficam no domínio e no caso de uso.
 */
final class CreateClassSeriesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'instructor_id' => ['required', 'string', 'ulid'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => [
                'required', 'integer',
                'between:'.WeeklyRecurrence::MIN_DURATION_MINUTES.','.WeeklyRecurrence::MAX_DURATION_MINUTES,
            ],
            'capacity' => ['required', 'integer', 'between:1,200'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    public function toData(): CreateClassSeriesData
    {
        return new CreateClassSeriesData(
            title: $this->string('title')->toString(),
            instructorId: $this->string('instructor_id')->toString(),
            weekdays: array_map(intval(...), array_values((array) $this->input('weekdays'))),
            startTime: $this->string('start_time')->toString(),
            durationMinutes: $this->integer('duration_minutes'),
            capacity: $this->integer('capacity'),
            startsOn: $this->string('starts_on')->toString(),
            endsOn: $this->string('ends_on')->toString(),
        );
    }
}
