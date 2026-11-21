<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Jwt\InvalidToken;
use App\Modules\Identity\Jwt\JwtVerifier;
use App\Modules\Identity\Jwt\KeySetProvider;
use App\Modules\Identity\Jwt\StaticKeySetProvider;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\JwtFactory;
use Tests\TestCase;

final class JwtVerifierTest extends TestCase
{
    private function verifier(?KeySetProvider $keys = null): JwtVerifier
    {
        return new JwtVerifier(
            keys: $keys ?? new StaticKeySetProvider(JwtFactory::KID, JwtFactory::publicKey()),
            issuer: JwtFactory::ISSUER,
            audience: JwtFactory::AUDIENCE,
            leewaySeconds: 30,
            tenantClaim: 'tenant',
        );
    }

    #[Test]
    public function it_returns_the_claims_of_a_valid_token(): void
    {
        $token = JwtFactory::token([
            'sub' => 'user-1',
            'tenant' => 'estudio-lotus',
            'email' => 'aluna@example.com',
            'preferred_username' => 'aluna',
            'realm_access' => ['roles' => ['student', 'offline_access', 42]],
        ]);

        $claims = $this->verifier()->verify($token);

        $this->assertSame('user-1', $claims->subject);
        $this->assertSame('estudio-lotus', $claims->tenant);
        $this->assertSame(['student', 'offline_access'], $claims->roles);
        $this->assertSame('aluna@example.com', $claims->email);
        $this->assertSame('aluna', $claims->name);
    }

    #[Test]
    public function it_accepts_a_single_string_audience(): void
    {
        $claims = $this->verifier()->verify(JwtFactory::token(['sub' => 's', 'tenant' => 't', 'aud' => JwtFactory::AUDIENCE]));

        $this->assertSame('s', $claims->subject);
    }

    #[Test]
    public function it_tolerates_clock_skew_within_the_leeway(): void
    {
        $token = JwtFactory::token(['sub' => 's', 'tenant' => 't', 'exp' => now()->getTimestamp() - 10]);

        $this->assertSame('s', $this->verifier()->verify($token)->subject);
    }

    /**
     * @return iterable<string, array{\Closure(): string, string}>
     */
    public static function rejectedTokens(): iterable
    {
        $base = ['sub' => 'user-1', 'tenant' => 'estudio-lotus'];

        yield 'expirado' => [fn () => JwtFactory::token($base + ['exp' => now()->getTimestamp() - 60]), 'token expirado'];
        yield 'ainda não válido' => [fn () => JwtFactory::token($base + ['nbf' => now()->getTimestamp() + 120]), 'token ainda não válido'];
        yield 'sem exp' => [fn () => JwtFactory::token($base + ['exp' => null]), 'exp ausente'];
        yield 'emissor errado' => [fn () => JwtFactory::token($base + ['iss' => 'https://evil.example.com']), 'emissor inesperado'];
        yield 'audiência errada' => [fn () => JwtFactory::token($base + ['aud' => ['account']]), 'audiência inesperada'];
        yield 'sem sub' => [fn () => JwtFactory::token(['tenant' => 'estudio-lotus']), 'sub ausente'];
        yield 'sem tenant' => [fn () => JwtFactory::token(['sub' => 'user-1']), 'claim de tenant ausente'];
        yield 'tenant não textual' => [fn () => JwtFactory::token(['sub' => 'user-1', 'tenant' => ['a']]), 'claim de tenant ausente'];
        yield 'kid desconhecido' => [fn () => JwtFactory::token($base, ['kid' => 'outra-chave']), 'kid desconhecido'];
        yield 'sem kid' => [fn () => self::unsignedWithHeader(['alg' => 'RS256', 'typ' => 'JWT']), 'kid ausente'];
        yield 'assinado por outra chave' => [
            fn () => JwtFactory::token($base, [], JwtFactory::newKeyPair()['private']),
            'assinatura inválida',
        ];
        yield 'alg none' => [fn () => self::unsignedWithHeader(['alg' => 'none', 'kid' => JwtFactory::KID]), 'algoritmo não permitido'];
        yield 'confusão HS256 com a chave pública' => [
            fn () => JwtFactory::token($base, [], JwtFactory::publicKey(), 'HS256'),
            'algoritmo não permitido',
        ];
        yield 'formato inválido' => [fn () => 'abc.def', 'formato inválido'];
        yield 'cabeçalho ilegível' => [fn () => 'bm90LWpzb24.e30.sig', 'cabeçalho ilegível'];
    }

    #[Test]
    #[DataProvider('rejectedTokens')]
    public function it_rejects_invalid_tokens(\Closure $token, string $reason): void
    {
        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessage($reason);

        $this->verifier()->verify($token());
    }

    #[Test]
    public function it_rejects_a_tampered_payload(): void
    {
        [$header, , $signature] = explode('.', JwtFactory::token(['sub' => 'aluno', 'tenant' => 'estudio-lotus']));
        $forged = JWT::urlsafeB64Encode((string) json_encode([
            'sub' => 'aluno', 'tenant' => 'outro-estudio', 'iss' => JwtFactory::ISSUER,
            'aud' => JwtFactory::AUDIENCE, 'exp' => now()->getTimestamp() + 300,
        ]));

        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessage('assinatura inválida');

        $this->verifier()->verify("{$header}.{$forged}.{$signature}");
    }

    #[Test]
    public function an_unknown_kid_triggers_one_key_refresh_for_rotation(): void
    {
        $rotated = new class implements KeySetProvider
        {
            public int $refreshes = 0;

            public function keys(): array
            {
                return [];
            }

            public function refresh(): array
            {
                $this->refreshes++;

                return [JwtFactory::KID => new Key(JwtFactory::publicKey(), 'RS256')];
            }
        };

        $claims = $this->verifier($rotated)->verify(JwtFactory::token(['sub' => 's', 'tenant' => 't']));

        $this->assertSame('s', $claims->subject);
        $this->assertSame(1, $rotated->refreshes);
    }

    /**
     * @param  array<string, string>  $header
     */
    private static function unsignedWithHeader(array $header): string
    {
        $encode = fn (array $data): string => JWT::urlsafeB64Encode((string) json_encode($data));

        return $encode($header).'.'.$encode(['sub' => 'x', 'tenant' => 'y', 'exp' => time() + 300]).'.';
    }
}
