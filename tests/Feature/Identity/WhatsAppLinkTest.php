<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\WhatsAppLink;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\JwtFactory;
use Tests\TestCase;

final class WhatsAppLinkTest extends TestCase
{
    use RefreshDatabase;

    private const string CONTACT = '5511999990001';

    private Tenant $lotus;

    private Tenant $equilibrio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
        $this->equilibrio = $this->createTenant('estudio-equilibrio');
    }

    #[Test]
    public function a_student_issues_a_six_digit_code_but_staff_cannot(): void
    {
        $student = $this->createUser($this->lotus, Role::Student);

        $this->actingAsJwt($student)->postJson('/api/v1/me/whatsapp-link/code')
            ->assertCreated()
            ->assertJsonPath('data.code', fn (string $code): bool => preg_match('/^\d{6}$/', $code) === 1)
            ->assertJsonStructure(['data' => ['code', 'expires_at']]);

        foreach ([Role::StudioAdmin, Role::Instructor] as $role) {
            $this->actingAsJwt($this->createUser($this->lotus, $role))
                ->postJson('/api/v1/me/whatsapp-link/code')
                ->assertForbidden();
        }
    }

    #[Test]
    public function the_agent_confirms_a_valid_code_and_the_phone_is_stored_only_as_a_hash(): void
    {
        $student = $this->createUser($this->lotus, Role::Student, 'Aluna Exemplo');
        $code = $this->issueCode($student);

        $this->confirm($code, self::CONTACT)
            ->assertCreated()
            ->assertJsonPath('data.member_name', 'Aluna Exemplo')
            ->assertJsonStructure(['data' => ['linked_at']]);

        $this->actingAsJwt($student)->getJson('/api/v1/me/whatsapp-link')
            ->assertOk()
            ->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.code_pending', false);

        $row = WhatsAppLink::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertStringNotContainsString(self::CONTACT, (string) json_encode($row->getAttributes()));
        $this->assertNull($row->code_hash);
    }

    #[Test]
    public function wrong_expired_and_reused_codes_are_rejected(): void
    {
        $student = $this->createUser($this->lotus, Role::Student);
        $code = $this->issueCode($student);

        $this->confirm($this->otherCode($code), self::CONTACT)
            ->assertUnprocessable()->assertJsonPath('type', 'invalid_link_code');

        $this->confirm($code, self::CONTACT)->assertCreated();
        $this->confirm($code, self::CONTACT)
            ->assertUnprocessable()->assertJsonPath('type', 'invalid_link_code');

        $expiring = $this->issueCode($student);
        $this->travel(11)->minutes();
        $this->confirm($expiring, self::CONTACT)
            ->assertUnprocessable()->assertJsonPath('type', 'invalid_link_code');
    }

    #[Test]
    public function the_confirmation_endpoint_requires_the_agent_role(): void
    {
        $student = $this->createUser($this->lotus, Role::Student);
        $code = $this->issueCode($student);

        $this->actingAsJwt($student)
            ->postJson('/api/v1/integrations/whatsapp/links', ['code' => $code, 'contact' => self::CONTACT])
            ->assertForbidden()->assertJsonPath('type', 'whatsapp_agent_required');

        $this->postJson('/api/v1/integrations/whatsapp/links', [], ['Authorization' => ''])
            ->assertUnauthorized();
    }

    #[Test]
    public function a_code_from_another_studio_does_not_link(): void
    {
        $student = $this->createUser($this->lotus, Role::Student);
        $code = $this->issueCode($student);

        $this->withToken($this->agentToken($this->equilibrio))
            ->postJson('/api/v1/integrations/whatsapp/links', ['code' => $code, 'contact' => self::CONTACT])
            ->assertUnprocessable()->assertJsonPath('type', 'invalid_link_code');
    }

    #[Test]
    public function the_agent_acts_as_the_linked_student(): void
    {
        $student = $this->createUser($this->lotus, Role::Student, 'Aluna Exemplo');
        $this->confirm($this->issueCode($student), self::CONTACT)->assertCreated();

        $this->withToken($this->agentToken($this->lotus))
            ->withHeader('X-WhatsApp-Contact', self::CONTACT)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Aluna Exemplo')
            ->assertJsonPath('data.role', 'student');

        $this->withToken($this->agentToken($this->lotus))
            ->withHeader('X-WhatsApp-Contact', self::CONTACT)
            ->getJson('/api/v1/me/bookings')
            ->assertOk();
    }

    #[Test]
    public function unlinked_contacts_and_missing_headers_are_forbidden(): void
    {
        $student = $this->createUser($this->lotus, Role::Student);
        $this->confirm($this->issueCode($student), self::CONTACT)->assertCreated();
        $token = $this->agentToken($this->lotus);

        $this->withToken($token)->withHeader('X-WhatsApp-Contact', '5511888880000')
            ->getJson('/api/v1/me')
            ->assertForbidden()->assertJsonPath('type', 'whatsapp_contact_not_linked');

        $this->flushHeaders()->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()->assertJsonPath('type', 'role_not_recognized');
    }

    #[Test]
    public function a_student_of_another_studio_with_the_same_phone_is_not_reached(): void
    {
        $other = $this->createUser($this->equilibrio, Role::Student);
        $code = $this->issueCode($other);
        $this->flushHeaders()->withToken($this->agentToken($this->equilibrio))
            ->postJson('/api/v1/integrations/whatsapp/links', ['code' => $code, 'contact' => self::CONTACT])
            ->assertCreated();

        $this->withToken($this->agentToken($this->lotus))
            ->withHeader('X-WhatsApp-Contact', self::CONTACT)
            ->getJson('/api/v1/me')
            ->assertForbidden()->assertJsonPath('type', 'whatsapp_contact_not_linked');
    }

    #[Test]
    public function the_phone_moves_to_whoever_proves_the_code_last(): void
    {
        $first = $this->createUser($this->lotus, Role::Student, 'Primeira Exemplo');
        $second = $this->createUser($this->lotus, Role::Student, 'Segunda Exemplo');

        $this->confirm($this->issueCode($first), self::CONTACT)->assertCreated();
        $this->confirm($this->issueCode($second), self::CONTACT)->assertCreated();

        $this->actingAsJwt($first)->getJson('/api/v1/me/whatsapp-link')->assertJsonPath('data.linked', false);
        $this->withToken($this->agentToken($this->lotus))
            ->withHeader('X-WhatsApp-Contact', self::CONTACT)
            ->getJson('/api/v1/me')->assertJsonPath('data.name', 'Segunda Exemplo');
    }

    #[Test]
    public function deleting_the_link_removes_the_delegation(): void
    {
        $student = $this->createUser($this->lotus, Role::Student);
        $this->confirm($this->issueCode($student), self::CONTACT)->assertCreated();

        $this->actingAsJwt($student)->deleteJson('/api/v1/me/whatsapp-link')->assertNoContent();

        $this->withToken($this->agentToken($this->lotus))
            ->withHeader('X-WhatsApp-Contact', self::CONTACT)
            ->getJson('/api/v1/me')
            ->assertForbidden()->assertJsonPath('type', 'whatsapp_contact_not_linked');
    }

    #[Test]
    public function a_student_or_admin_token_ignores_the_contact_header(): void
    {
        $student = $this->createUser($this->lotus, Role::Student, 'Aluna Exemplo');
        $admin = $this->createUser($this->lotus, Role::StudioAdmin, 'Admin Exemplo');
        $this->confirm($this->issueCode($student), self::CONTACT)->assertCreated();

        $this->actingAsJwt($admin)->withHeader('X-WhatsApp-Contact', self::CONTACT)
            ->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.name', 'Admin Exemplo');
    }

    private function issueCode(User $student): string
    {
        return (string) $this->actingAsJwt($student)
            ->postJson('/api/v1/me/whatsapp-link/code')->assertCreated()->json('data.code');
    }

    private function otherCode(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return TestResponse<Response>
     */
    private function confirm(string $code, string $contact): TestResponse
    {
        return $this->flushHeaders()->withToken($this->agentToken($this->lotus))
            ->postJson('/api/v1/integrations/whatsapp/links', ['code' => $code, 'contact' => $contact]);
    }

    private function agentToken(Tenant $tenant): string
    {
        return JwtFactory::token([
            'sub' => 'service-account-whatsapp-agent-'.$tenant->slug,
            'tenant' => $tenant->slug,
            'realm_access' => ['roles' => ['whatsapp_agent']],
        ]);
    }
}
