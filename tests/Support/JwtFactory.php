<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Date;
use RuntimeException;

/**
 * Emite tokens RS256 como o Keycloak emitiria, assinados por um par de chaves
 * gerado em memória uma vez por processo. Nenhuma chave privada é versionada.
 */
final class JwtFactory
{
    public const string KID = 'test-key';

    public const string ISSUER = 'https://idp.example.com/realms/estudios';

    public const string AUDIENCE = 'studio-api';

    private static ?string $privateKey = null;

    private static ?string $publicKey = null;

    public static function publicKey(): string
    {
        self::ensureKeys();

        return (string) self::$publicKey;
    }

    public static function privateKey(): string
    {
        self::ensureKeys();

        return (string) self::$privateKey;
    }

    /**
     * Gera um par RSA avulso (ex.: para simular um atacante com outra chave).
     *
     * @return array{private: string, public: string}
     */
    public static function newKeyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($resource === false || ! openssl_pkey_export($resource, $private)) {
            throw new RuntimeException('Falha ao gerar par RSA de teste.');
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false) {
            throw new RuntimeException('Falha ao extrair chave pública de teste.');
        }

        return ['private' => $private, 'public' => $details['key']];
    }

    /**
     * @param  array<string, mixed>  $overrides  Claims a sobrescrever; valor null remove a claim.
     */
    public static function forUser(User $user, Tenant $tenant, array $overrides = []): string
    {
        return self::token(array_merge([
            'sub' => $user->subject,
            'tenant' => $tenant->slug,
            'email' => $user->email,
            'name' => $user->name,
            'realm_access' => ['roles' => [$user->role->value, 'offline_access']],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $header
     */
    public static function token(array $claims, array $header = [], ?string $privateKey = null, string $alg = 'RS256'): string
    {
        $now = Date::now()->getTimestamp();

        $payload = array_merge([
            'iss' => self::ISSUER,
            'aud' => [self::AUDIENCE, 'account'],
            'iat' => $now,
            'exp' => $now + 300,
            'realm_access' => ['roles' => [Role::Student->value]],
        ], $claims);

        $payload = array_filter($payload, fn (mixed $value): bool => $value !== null);

        return JWT::encode($payload, $privateKey ?? self::privateKey(), $alg, $header['kid'] ?? self::KID, $header);
    }

    private static function ensureKeys(): void
    {
        if (self::$privateKey !== null) {
            return;
        }

        ['private' => self::$privateKey, 'public' => self::$publicKey] = self::newKeyPair();
    }
}
