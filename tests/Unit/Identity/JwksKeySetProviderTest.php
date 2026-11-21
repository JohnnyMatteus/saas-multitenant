<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Exceptions\IdentityProviderUnavailable;
use App\Modules\Identity\Jwt\JwksKeySetProvider;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http as HttpFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\JwtFactory;
use Tests\TestCase;

/**
 * Nenhuma chamada sai da máquina: o endpoint JWKS é simulado com Http::fake.
 */
final class JwksKeySetProviderTest extends TestCase
{
    private const string URI = 'https://idp.example.com/realms/estudios/protocol/openid-connect/certs';

    private function provider(int $minRefresh = 30): JwksKeySetProvider
    {
        return new JwksKeySetProvider(
            http: $this->app->make(Http::class),
            cache: Cache::store(),
            uri: self::URI,
            cacheTtlSeconds: 600,
            minRefreshIntervalSeconds: $minRefresh,
            timeoutSeconds: 1,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(string $kid = JwtFactory::KID): array
    {
        $key = openssl_pkey_get_public(JwtFactory::publicKey());
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($details === false) {
            throw new \RuntimeException('Chave pública de teste ilegível.');
        }

        return ['keys' => [
            [
                'kid' => $kid, 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig',
                'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
            ],
            // Chave de cifragem que o Keycloak também publica: deve ser ignorada.
            ['kid' => 'enc-key', 'kty' => 'RSA', 'alg' => 'RSA-OAEP', 'use' => 'enc', 'n' => 'AQAB', 'e' => 'AQAB'],
        ]];
    }

    #[Test]
    public function it_fetches_parses_and_caches_signing_keys(): void
    {
        HttpFacade::fake([self::URI => HttpFacade::response($this->jwks())]);
        $provider = $this->provider();

        $first = $provider->keys();
        $second = $provider->keys();

        $this->assertSame([JwtFactory::KID], array_keys($first));
        $this->assertEquals($first, $second);

        HttpFacade::assertSentCount(1);
    }

    #[Test]
    public function forced_refreshes_are_rate_limited(): void
    {
        HttpFacade::fake([self::URI => HttpFacade::response($this->jwks())]);
        $provider = $this->provider();

        $provider->refresh();
        $provider->refresh();
        $provider->refresh();

        HttpFacade::assertSentCount(1);
    }

    #[Test]
    public function a_refresh_picks_up_a_rotated_key(): void
    {
        HttpFacade::fake([self::URI => HttpFacade::sequence()
            ->push($this->jwks('old-key'))
            ->push($this->jwks('new-key'))]);
        $provider = $this->provider();

        $this->assertArrayHasKey('old-key', $provider->keys());
        $this->assertArrayHasKey('new-key', $provider->refresh());
    }

    #[Test]
    public function it_fails_explicitly_when_the_identity_provider_is_down(): void
    {
        HttpFacade::fake([self::URI => HttpFacade::response('erro', 503)]);

        $this->expectException(IdentityProviderUnavailable::class);

        $this->provider()->keys();
    }

    #[Test]
    public function it_fails_explicitly_on_a_malformed_document(): void
    {
        HttpFacade::fake([self::URI => HttpFacade::response(['chaves' => []])]);

        $this->expectException(IdentityProviderUnavailable::class);

        $this->provider()->keys();
    }
}
