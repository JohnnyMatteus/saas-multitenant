<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\ClassSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma ocorrência concreta de aula, com horário em UTC e capacidade própria.
 * É a linha travada (SELECT ... FOR UPDATE) para serializar reservas.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $series_id
 * @property string $instructor_id
 * @property string $title
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $capacity
 * @property-read int $confirmed_count
 * @property-read int $waitlist_count
 * @property-read User $instructor
 */
class ClassSession extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ClassSessionFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = ['tenant_id', 'series_id', 'instructor_id', 'title', 'starts_at', 'ends_at', 'capacity'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'capacity' => 'integer',
            'confirmed_count' => 'integer',
            'waitlist_count' => 'integer',
        ];
    }

    public function hasStartedAt(CarbonImmutable $now): bool
    {
        return $now->greaterThanOrEqualTo($this->starts_at);
    }

    /**
     * Carrega contadores de vagas sem N+1.
     *
     * @param  Builder<ClassSession>  $query
     */
    public function scopeWithSeatCounts(Builder $query): void
    {
        $query->withCount([
            'bookings as confirmed_count' => fn (Builder $q) => $q->where('status', BookingStatus::Confirmed),
            'bookings as waitlist_count' => fn (Builder $q) => $q->where('status', BookingStatus::Waitlisted),
        ]);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * @return BelongsTo<ClassSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(ClassSeries::class, 'series_id');
    }

    protected static function newFactory(): ClassSessionFactory
    {
        return ClassSessionFactory::new();
    }
}
