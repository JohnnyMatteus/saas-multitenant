<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reserva de um aluno numa aula: vaga confirmada, lugar na fila ou cancelada.
 *
 * Há no máximo uma linha por (aula, aluno); recancelar e reservar de novo
 * reaproveita a linha. A ordem da fila é queued_at (microssegundos) + id.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $session_id
 * @property string $member_id
 * @property BookingStatus $status
 * @property CarbonImmutable $queued_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $canceled_at
 * @property-read ClassSession $session
 * @property-read User $member
 */
class Booking extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['tenant_id', 'session_id', 'member_id', 'status', 'queued_at', 'confirmed_at', 'canceled_at'];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'queued_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
        ];
    }

    /**
     * Posição 1-based na fila da aula, na mesma ordem da promoção
     * (queued_at, depois id). Nulo para quem não está na fila.
     */
    public function waitlistPosition(): ?int
    {
        if ($this->status !== BookingStatus::Waitlisted) {
            return null;
        }

        $ahead = self::query()
            ->where('session_id', $this->session_id)
            ->where('status', BookingStatus::Waitlisted)
            ->where(fn (Builder $q) => $q
                ->where('queued_at', '<', $this->queued_at)
                ->orWhere(fn (Builder $q) => $q->where('queued_at', $this->queued_at)->where('id', '<', $this->id)))
            ->count();

        return $ahead + 1;
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
