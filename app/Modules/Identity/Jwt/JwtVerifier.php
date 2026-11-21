<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jwt;

use DomainException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use stdClass;
use UnexpectedValueException;

/**
 * Valida um access token OIDC: assinatura RS256, exp/nbf, emissor, audiência
 * e presença das claims de que o domínio depende.
 */
final readonly class JwtVerifier
{
    private const string ALGORITHM = 'RS256';

    public function __construct(
        private KeySetProvider $keys,
        private string $issuer,
        private string $audience,
        private int $leewaySeconds,
        private string $tenantClaim,
    ) {}

    public function verify(string $token): VerifiedClaims
    {
        $header = $this->header($token);

        // Recusa explícita antes de tocar na chave: bloqueia "alg: none" e a
        // confusão RS256/HS256 (usar a chave pública como segredo HMAC).
        if (($header['alg'] ?? null) !== self::ALGORITHM) {
            throw InvalidToken::because('algoritmo não permitido');
        }

        $kid = $header['kid'] ?? null;
        if (! is_string($kid) || $kid === '') {
            throw InvalidToken::because('kid ausente');
        }

        return $this->claims($this->decode($token, $kid));
    }

    /**
     * @return array<string, mixed>
     */
    private function header(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw InvalidToken::because('formato inválido');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
        } catch (DomainException|InvalidArgumentException) {
            throw InvalidToken::because('cabeçalho ilegível');
        }

        if (! $header instanceof stdClass) {
            throw InvalidToken::because('cabeçalho ilegível');
        }

        return (array) $header;
    }

    private function decode(string $token, string $kid): stdClass
    {
        $keys = $this->keys->keys();
        if (! isset($keys[$kid])) {
            $keys = $this->keys->refresh();
        }

        $key = $keys[$kid] ?? throw InvalidToken::because('kid desconhecido');

        JWT::$leeway = $this->leewaySeconds;
        // Relógio da aplicação (e não time()) para exp/nbf, o que permite testar expiração.
        JWT::$timestamp = Date::now()->getTimestamp();

        try {
            return JWT::decode($token, $key);
        } catch (ExpiredException) {
            throw InvalidToken::because('token expirado');
        } catch (BeforeValidException) {
            throw InvalidToken::because('token ainda não válido');
        } catch (SignatureInvalidException) {
            throw InvalidToken::because('assinatura inválida');
        } catch (UnexpectedValueException|DomainException|InvalidArgumentException $e) {
            throw InvalidToken::because('token malformado: '.$e->getMessage());
        }
    }

    private function claims(stdClass $payload): VerifiedClaims
    {
        if (! isset($payload->exp)) {
            throw InvalidToken::because('exp ausente');
        }

        if (($payload->iss ?? null) !== $this->issuer) {
            throw InvalidToken::because('emissor inesperado');
        }

        $audiences = (array) ($payload->aud ?? []);
        if (! in_array($this->audience, $audiences, true)) {
            throw InvalidToken::because('audiência inesperada');
        }

        $subject = $payload->sub ?? null;
        if (! is_string($subject) || $subject === '') {
            throw InvalidToken::because('sub ausente');
        }

        $tenant = $payload->{$this->tenantClaim} ?? null;
        if (! is_string($tenant) || $tenant === '') {
            throw InvalidToken::because('claim de tenant ausente');
        }

        return new VerifiedClaims(
            subject: $subject,
            tenant: $tenant,
            roles: $this->roles($payload),
            email: is_string($payload->email ?? null) ? $payload->email : null,
            name: $this->name($payload, $subject),
        );
    }

    /**
     * Keycloak publica as realm roles em realm_access.roles.
     *
     * @return list<string>
     */
    private function roles(stdClass $payload): array
    {
        $roles = $payload->realm_access->roles ?? [];

        return is_array($roles) ? array_values(array_filter($roles, is_string(...))) : [];
    }

    private function name(stdClass $payload, string $fallback): string
    {
        foreach (['name', 'preferred_username'] as $claim) {
            if (is_string($payload->{$claim} ?? null) && $payload->{$claim} !== '') {
                return $payload->{$claim};
            }
        }

        return $fallback;
    }
}
