<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\ProvisionUser;
use App\Modules\Identity\Exceptions\RoleNotRecognized;
use App\Modules\Identity\Exceptions\TenantNotRecognized;
use App\Modules\Identity\Jwt\InvalidToken;
use App\Modules\Identity\Jwt\JwtVerifier;
use App\Modules\Identity\Models\Role;
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

        $role = Role::highestFrom($claims->roles) ?? throw new RoleNotRecognized;

        $this->tenantContext->set($tenant);

        try {
            $user = $this->provisionUser->handle($claims, $role);
            $this->auth->guard()->setUser($user);
            Context::add(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

            return $next($request);
        } finally {
            // Nada da identidade sobrevive à requisição (workers longos e testes).
            $this->auth->forgetGuards();
            $this->tenantContext->clear();
        }
    }
}
