<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aula recorrente (ex.: "Hatha Yoga, terças e quintas às 07:00").
 * As ocorrências são materializadas em ClassSession na criação.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $instructor_id
 * @property string $title
 * @property list<int> $weekdays
 * @property string $start_time
 * @property int $duration_minutes
 * @property int $capacity
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $rolling
 */
class ClassSeries extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $table = 'class_series';

    protected $fillable = [
        'tenant_id', 'instructor_id', 'title', 'weekdays', 'start_time',
        'duration_minutes', 'capacity', 'starts_on', 'ends_on', 'rolling',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'rolling' => 'boolean',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'series_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
