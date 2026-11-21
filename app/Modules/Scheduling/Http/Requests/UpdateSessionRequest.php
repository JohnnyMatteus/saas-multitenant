<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Scheduling\Application\SeriesEditData;
use App\Modules\Scheduling\Domain\SeriesEditScope;
use App\Modules\Scheduling\Domain\WeeklyRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSessionRequest extends FormRequest
{
    private const array FIELDS = ['title', 'instructor_id', 'capacity', 'start_time', 'duration_minutes'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::enum(SeriesEditScope::class)],
            'title' => ['sometimes', 'string', 'max:120'],
            'instructor_id' => ['sometimes', 'string', 'ulid'],
            'capacity' => ['sometimes', 'integer', 'between:1,200'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'duration_minutes' => [
                'sometimes', 'integer',
                'between:'.WeeklyRecurrence::MIN_DURATION_MINUTES.','.WeeklyRecurrence::MAX_DURATION_MINUTES,
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->hasAny(self::FIELDS) === false) {
                    $validator->errors()->add('scope', 'Informe ao menos um campo para alterar.');
                }
            },
        ];
    }

    public function scope(): SeriesEditScope
    {
        return SeriesEditScope::from($this->string('scope')->toString());
    }

    public function toData(): SeriesEditData
    {
        return new SeriesEditData(
            title: $this->has('title') ? $this->string('title')->toString() : null,
            instructorId: $this->has('instructor_id') ? $this->string('instructor_id')->toString() : null,
            capacity: $this->has('capacity') ? $this->integer('capacity') : null,
            startTime: $this->has('start_time') ? $this->string('start_time')->toString() : null,
            durationMinutes: $this->has('duration_minutes') ? $this->integer('duration_minutes') : null,
        );
    }
}
