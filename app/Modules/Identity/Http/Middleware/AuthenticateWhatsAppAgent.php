<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Exceptions\TenantNotRecognized;
use App\Modules\Identity\Exceptions\WhatsAppAgentRequired;
use App\Modules\Identity\Jwt\InvalidToken;
use App\Modules\Identity\Jwt\JwtVerifier;
use App\Modules\Identity\WhatsApp\WhatsAppAgent;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrada da conta de serviço do agente nas rotas de integração: define o
 * tenant pela claim e não autentica nenhum usuário.
 */
final readonly class AuthenticateWhatsAppAgent
{
    public function __construct(
        private JwtVerifier $verifier,
        private TenantContext $tenantContext,
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

        if (! in_array(WhatsAppAgent::ROLE, $claims->roles, true)) {
            throw new WhatsAppAgentRequired;
        }

        $tenant = Tenant::query()->where('slug', $claims->tenant)->where('active', true)->first()
            ?? throw new TenantNotRecognized;

        $this->tenantContext->set($tenant);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
