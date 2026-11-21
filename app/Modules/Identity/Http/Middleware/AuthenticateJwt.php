<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\ProvisionUser;
use App\Modules\Identity\Exceptions\RoleNotRecognized;
use App\Modules\Identity\Exceptions\TenantNotRecognized;
use App\Modules\Identity\Exceptions\WhatsAppContactNotLinked;
use App\Modules\Identity\Jwt\InvalidToken;
use App\Modules\Identity\Jwt\JwtVerifier;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\WhatsApp\WhatsAppAgent;
use App\Modules\Identity\WhatsApp\WhatsAppLinkService;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta de entrada de toda rota de domínio:
 * token -> claims validadas -> tenant -> papel -> usuário provisionado.
 *
 * O tenant vem exclusivamente da claim assinada pelo IdP, nunca de header,
 * subdomínio ou parâmetro controlado pelo cliente.
 */
final readonly class AuthenticateJwt implements AuthenticatesRequests
{
    public function __construct(
        private JwtVerifier $verifier,
        private TenantContext $tenantContext,
        private ProvisionUser $provisionUser,
        private AuthManager $auth,
        private WhatsAppLinkService $whatsAppLinks,
        private LoggerInterface $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            throw new AuthenticationException('Token Bearer ausente.');
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (InvalidToken $e) {
            $this->logger->notice('jwt_rejected', ['reason' => $e->getMessage()]);

            throw new AuthenticationException('Token inválido.');
        }

        $tenant = Tenant::query()->where('slug', $claims->tenant)->where('active', true)->first()
            ?? throw new TenantNotRecognized;

        $role = Role::highestFrom($claims->roles);
        $contact = $request->header(WhatsAppAgent::CONTACT_HEADER);
        $delegating = $role === null && $contact !== null && in_array(WhatsAppAgent::ROLE, $claims->roles, true);
        if ($role === null && ! $delegating) {
            throw new RoleNotRecognized;
        }

        $this->tenantContext->set($tenant);

        try {
            $user = $role === null
                ? $this->delegatedStudent((string) $contact)
                : $this->provisionUser->handle($claims, $role);
            $this->auth->guard()->setUser($user);
            Context::add(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

            return $next($request);
        } finally {
            // Nada da identidade sobrevive à requisição (workers longos e testes).
            $this->auth->forgetGuards();
            $this->tenantContext->clear();
        }
    }

    /**
     * A conta de serviço do agente age como o aluno vinculado ao telefone do remetente,
     * com as mesmas policies e o mesmo isolamento do aluno.
     */
    private function delegatedStudent(string $contact): User
    {
        if (preg_match(WhatsAppAgent::CONTACT_PATTERN, $contact) !== 1) {
            throw new WhatsAppContactNotLinked;
        }

        return $this->whatsAppLinks->studentForContact($contact) ?? throw new WhatsAppContactNotLinked;
    }
}
