<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtro por datas locais do estúdio ("from"/"to" inclusivos, Y-m-d).
 */
final class ListSessionsRequest extends FormRequest
{
    public const int MAX_RANGE_DAYS = 31;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /**
     * Converte o intervalo local para instantes UTC.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function utcRange(string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->toDateString();
        $from = CarbonImmutable::parse($this->string('from', $today)->toString(), $timezone)->startOfDay();
        $to = $this->has('to')
            ? CarbonImmutable::parse($this->string('to')->toString(), $timezone)->endOfDay()
            : $from->addDays(6)->endOfDay();

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $to = $from->addDays(self::MAX_RANGE_DAYS)->endOfDay();
        }

        return [$from->utc(), $to->utc()];
    }
}
