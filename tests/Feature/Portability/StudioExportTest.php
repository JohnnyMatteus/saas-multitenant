<?php

declare(strict_types=1);

namespace Tests\Feature\Portability;

use App\Modules\Billing\Domain\OrderStatus;
use App\Modules\Billing\Models\LessonPackage;
use App\Modules\Billing\Models\PackageOrder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\WhatsAppLink;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Support\CapturingLogger;
use Tests\TestCase;

final class StudioExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private Tenant $equilibrio;

    private User $lotusAdmin;

    private User $lotusStudent;

    private User $lotusInstructor;

    private User $equilibrioAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
        $this->equilibrio = $this->createTenant('pilates-equilibrio');
        $this->lotusAdmin = $this->createUser($this->lotus, Role::StudioAdmin, 'Ana Admin Exemplo');
        $this->lotusInstructor = $this->createUser($this->lotus, Role::Instructor, 'Igor Exemplo');
        $this->lotusStudent = $this->createUser($this->lotus, Role::Student, 'Aluna Exemplo');
        $this->equilibrioAdmin = $this->createUser($this->equilibrio, Role::StudioAdmin, 'Admin Equilíbrio Exemplo');
        $equilibrioStudent = $this->createUser($this->equilibrio, Role::Student, 'Aluno Equilíbrio Exemplo');

        $session = ClassSession::factory()->create(['tenant_id' => $this->lotus->id, 'instructor_id' => $this->lotusInstructor->id, 'title' => 'Hatha Lótus']);
        Booking::withoutTenancy()->create([
            'tenant_id' => $this->lotus->id, 'session_id' => $session->id, 'member_id' => $this->lotusStudent->id,
            'status' => BookingStatus::Confirmed, 'queued_at' => now(), 'confirmed_at' => now(),
        ]);
        WhatsAppLink::withoutTenancy()->create([
            'tenant_id' => $this->lotus->id, 'user_id' => $this->lotusStudent->id, 'contact_hash' => str_repeat('a', 64), 'linked_at' => now(),
        ]);
        $package = LessonPackage::query()->create([
            'tenant_id' => $this->lotus->id, 'name' => 'Pacote Lótus', 'lesson_count' => 8, 'price_cents' => 32000, 'currency' => 'BRL', 'active' => true,
        ]);
        PackageOrder::query()->create([
            'tenant_id' => $this->lotus->id, 'package_id' => $package->id, 'member_id' => $this->lotusStudent->id, 'payment_id' => 'pay_lotus_1',
            'lesson_count' => 8, 'amount_cents' => 32000, 'currency' => 'BRL', 'status' => OrderStatus::Pending,
        ]);

        ClassSession::factory()->create(['tenant_id' => $this->equilibrio->id, 'title' => 'Pilates Segredo Equilíbrio']);
        $foreignPackage = LessonPackage::query()->create([
            'tenant_id' => $this->equilibrio->id, 'name' => 'Pacote Equilíbrio', 'lesson_count' => 4, 'price_cents' => 11111, 'currency' => 'BRL', 'active' => true,
        ]);
        PackageOrder::query()->create([
            'tenant_id' => $this->equilibrio->id, 'package_id' => $foreignPackage->id, 'member_id' => $equilibrioStudent->id, 'payment_id' => 'pay_equilibrio_1',
            'lesson_count' => 4, 'amount_cents' => 11111, 'currency' => 'BRL', 'status' => OrderStatus::Pending,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function export(User $user): array
    {
        $response = $this->actingAsJwt($user)->get('/api/v1/studio/export')->assertOk();
        $decoded = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    #[Test]
    public function the_admin_downloads_a_complete_json_document_of_their_own_studio(): void
    {
        $response = $this->actingAsJwt($this->lotusAdmin)->get('/api/v1/studio/export')->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment; filename="estudio-lotus-', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        $document = $this->export($this->lotusAdmin);

        $this->assertSame(1, $document['format_version']);
        $this->assertSame('estudio-lotus', $document['tenant']['slug']);
        $this->assertCount(3, $document['users']);
        $this->assertCount(1, $document['class_sessions']);
        $this->assertCount(1, $document['bookings']);
        $this->assertCount(1, $document['lesson_packages']);
        $this->assertCount(1, $document['package_orders']);
        $this->assertSame('pay_lotus_1', $document['package_orders'][0]['payment_id']);
        $this->assertSame([], $document['payment_events']);
    }

    #[Test]
    public function the_document_never_contains_another_studios_data(): void
    {
        $raw = $this->actingAsJwt($this->lotusAdmin)->get('/api/v1/studio/export')->streamedContent();

        foreach (['Pilates Segredo Equilíbrio', 'Pacote Equilíbrio', 'pay_equilibrio_1', 'Admin Equilíbrio Exemplo', 'pilates-equilibrio'] as $foreign) {
            $this->assertStringNotContainsString($foreign, $raw);
        }

        $other = $this->export($this->equilibrioAdmin);
        $this->assertSame('pilates-equilibrio', $other['tenant']['slug']);
        $this->assertCount(1, $other['package_orders']);
        $this->assertSame('pay_equilibrio_1', $other['package_orders'][0]['payment_id']);
    }

    #[Test]
    public function whatsapp_links_are_left_out_of_the_export(): void
    {
        $raw = $this->actingAsJwt($this->lotusAdmin)->get('/api/v1/studio/export')->streamedContent();

        $this->assertStringNotContainsString(str_repeat('a', 64), $raw);
        $this->assertStringNotContainsString('contact_hash', $raw);
        $this->assertStringNotContainsString('code_hash', $raw);
    }

    #[Test]
    public function only_the_admin_exports_and_the_request_needs_a_token(): void
    {
        $this->actingAsJwt($this->lotusStudent)->get('/api/v1/studio/export')->assertForbidden();
        $this->actingAsJwt($this->lotusInstructor)->get('/api/v1/studio/export')->assertForbidden();
        $this->flushHeaders()->getJson('/api/v1/studio/export')->assertUnauthorized();
    }

    #[Test]
    public function a_header_cannot_pick_another_studio_to_export(): void
    {
        $raw = $this->actingAsJwt($this->lotusAdmin)
            ->withHeaders(['X-Tenant' => 'pilates-equilibrio', 'X-Tenant-Id' => $this->equilibrio->id])
            ->get('/api/v1/studio/export?tenant=pilates-equilibrio')->streamedContent();

        $this->assertStringContainsString('"slug":"estudio-lotus"', $raw);
        $this->assertStringNotContainsString('pay_equilibrio_1', $raw);
    }

    #[Test]
    public function every_export_is_audited_without_personal_data(): void
    {
        $logger = new CapturingLogger;
        $this->app->instance(LoggerInterface::class, $logger);

        $this->actingAsJwt($this->lotusAdmin)->get('/api/v1/studio/export')->streamedContent();

        $audit = $logger->named('studio_export');
        $this->assertCount(1, $audit);
        $this->assertSame('notice', $audit[0]['level']);
        $this->assertSame($this->lotus->id, $audit[0]['context']['tenant_id']);
        $this->assertSame($this->lotusAdmin->id, $audit[0]['context']['user_id']);
        $this->assertSame(3, $audit[0]['context']['rows']['users']);
        $this->assertStringNotContainsString('example.com', (string) json_encode($audit[0]['context']));
    }

    #[Test]
    public function exports_are_rate_limited_per_user(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAsJwt($this->lotusAdmin)->get('/api/v1/studio/export')->assertOk();
        }

        $this->actingAsJwt($this->lotusAdmin)->get('/api/v1/studio/export')->assertTooManyRequests();
        // O limite de um usuário não bloqueia o admin de outro estúdio.
        $this->actingAsJwt($this->equilibrioAdmin)->get('/api/v1/studio/export')->assertOk();
    }
}
