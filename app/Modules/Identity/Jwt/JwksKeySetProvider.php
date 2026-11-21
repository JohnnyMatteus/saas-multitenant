<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jwt;

use App\Modules\Identity\Exceptions\IdentityProviderUnavailable;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Lê o JWKS do IdP (Keycloak) com cache.
 *
 * O documento bruto fica em cache (objetos de chave OpenSSL não são
 * serializáveis). Um kid desconhecido dispara no máximo uma releitura por
 * intervalo mínimo, protegendo o IdP contra tokens com kids aleatórios.
 */
final readonly class JwksKeySetProvider implements KeySetProvider
{
    private const string CACHE_KEY = 'identity:jwks';

    private const string REFRESH_GUARD_KEY = 'identity:jwks:refresh-guard';

    public function __construct(
        private Http $http,
        private Cache $cache,
        private string $uri,
        private int $cacheTtlSeconds,
        private int $minRefreshIntervalSeconds,
        private int $timeoutSeconds,
    ) {}

    public function keys(): array
    {
        $jwks = $this->cache->get(self::CACHE_KEY);

        return $this->parse(is_array($jwks) ? $jwks : $this->fetchAndStore());
    }

    public function refresh(): array
    {
        if (! $this->cache->add(self::REFRESH_GUARD_KEY, true, $this->minRefreshIntervalSeconds)) {
            return $this->keys();
        }

        return $this->parse($this->fetchAndStore());
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAndStore(): array
    {
        try {
            $response = $this->http->timeout($this->timeoutSeconds)->acceptJson()->get($this->uri);
        } catch (ConnectionException $e) {
            throw IdentityProviderUnavailable::because($e->getMessage());
        }

        $jwks = $response->json();

        if ($response->failed() || ! is_array($jwks) || ! is_array($jwks['keys'] ?? null)) {
            throw IdentityProviderUnavailable::because("JWKS inválido (HTTP {$response->status()})");
        }

        $this->cache->put(self::CACHE_KEY, $jwks, $this->cacheTtlSeconds);

        return $jwks;
    }

    /**
     * @param  array<string, mixed>  $jwks
     * @return array<string, Key>
     */
    private function parse(array $jwks): array
    {
        /** @var list<array<string, mixed>> $keys */
        $keys = $jwks['keys'] ?? [];

        // O Keycloak publica também a chave de cifragem (use=enc); só assinatura interessa.
        $signingKeys = array_values(array_filter(
            $keys,
            fn (array $key): bool => ($key['use'] ?? 'sig') === 'sig' && ($key['kty'] ?? null) === 'RSA',
        ));

        return JWK::parseKeySet(['keys' => $signingKeys], 'RS256');
    }
}
