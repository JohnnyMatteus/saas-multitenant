<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Application\BookSession;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Shared\Exceptions\DomainError;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Corridas reais contra o MySQL: N processos (pcntl_fork), cada um com sua
 * própria conexão, disparam reservas no mesmo instante.
 *
 * Verificado removendo os lockForUpdate de BookSession: os testes passam a
 * falhar com deadlocks e reservas a mais (mais confirmados que vagas).
 */
#[RequiresPhpExtension('pcntl')]
#[RequiresPhpExtension('posix')]
final class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sem transação envolvendo o teste: os processos filhos precisam enxergar
     * os dados commitados. A limpeza é manual, no tearDown.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    private const int CONTENDERS = 8;

    private Tenant $tenant;

    private string $errorLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-03-01 12:00:00');
        $this->tenant = $this->createTenant('estudio-concorrencia');
        $this->errorLog = (string) tempnam(sys_get_temp_dir(), 'booking-race-');
    }

    protected function tearDown(): void
    {
        foreach (['bookings', 'class_sessions', 'class_series', 'users', 'tenants'] as $table) {
            DB::table($table)->delete();
        }
        @unlink($this->errorLog);

        parent::tearDown();
    }

    private function classSession(string $startsAtUtc, int $capacity): ClassSession
    {
        $startsAt = CarbonImmutable::parse($startsAtUtc, 'UTC');

        return ClassSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'capacity' => $capacity,
        ]);
    }

    #[Test]
    public function only_one_student_wins_the_last_seat(): void
    {
        $session = $this->classSession('2026-03-03 10:00:00', capacity: 1);
        $students = array_map(
            fn (int $i): User => $this->createUser($this->tenant, Role::Student, "Aluno {$i}"),
            range(1, self::CONTENDERS),
        );

        $this->race(array_map(
            fn (User $student): \Closure => fn () => $this->app->make(BookSession::class)->handle($student, $session->id),
            $students,
        ));

        $statuses = Booking::withoutTenancy()->where('session_id', $session->id)->pluck('status');

        $this->assertCount(self::CONTENDERS, $statuses, 'Toda tentativa deve gerar uma reserva.');
        $this->assertSame(1, $statuses->filter(fn (BookingStatus $s) => $s === BookingStatus::Confirmed)->count());
        $this->assertSame(self::CONTENDERS - 1, $statuses->filter(fn (BookingStatus $s) => $s === BookingStatus::Waitlisted)->count());
    }

    #[Test]
    public function the_same_student_cannot_win_two_overlapping_classes_at_once(): void
    {
        $student = $this->createUser($this->tenant, Role::Student, 'Aluna Apressada');
        $sessions = array_map(
            fn (int $i): ClassSession => $this->classSession('2026-03-03 10:00:00', capacity: 10),
            range(1, self::CONTENDERS),
        );

        $this->race(array_map(
            fn (ClassSession $session): \Closure => fn () => $this->app->make(BookSession::class)->handle($student, $session->id),
            $sessions,
        ));

        $this->assertSame(1, Booking::withoutTenancy()->where('member_id', $student->id)->count());
    }

    /**
     * Roda cada tarefa num processo filho, todas liberadas no mesmo instante.
     * Erros de domínio (ex.: OverlappingBooking) são o resultado esperado de
     * quem perde; qualquer outra exceção é registrada e falha o teste.
     *
     * @param  list<\Closure(): mixed>  $tasks
     */
    private function race(array $tasks): void
    {
        // A conexão do pai não pode ser herdada pelos filhos (mesmo socket).
        DB::disconnect();
        $startAt = microtime(true) + 0.5;
        $children = [];

        foreach ($tasks as $task) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork falhou.');
            }

            if ($pid === 0) {
                $this->runChild($task, $startAt);
            }

            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $this->assertSame('', (string) file_get_contents($this->errorLog), 'Erro inesperado num processo concorrente.');
    }

    /**
     * @param  \Closure(): mixed  $task
     */
    private function runChild(\Closure $task, float $startAt): never
    {
        try {
            DB::reconnect();
            usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));
            $this->app->make(TenantContext::class)->runAs($this->tenant, $task);
        } catch (DomainError) {
            // Perdedor legítimo da corrida.
        } catch (Throwable $e) {
            file_put_contents($this->errorLog, $e::class.': '.$e->getMessage().PHP_EOL, FILE_APPEND);
        } finally {
            DB::disconnect();
        }

        // Sai sem rodar shutdown handlers do PHPUnit herdados do pai.
        posix_kill(posix_getpid(), SIGKILL);
        exit(1);
    }
}
