<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Jwt\JwksKeySetProvider;
use App\Modules\Identity\Jwt\JwtVerifier;
use App\Modules\Identity\Jwt\KeySetProvider;
use App\Modules\Identity\Jwt\StaticKeySetProvider;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KeySetProvider::class, function (Application $app): KeySetProvider {
            $config = $app->make(Config::class);

            return match ($config->get('jwt.mode')) {
                'jwks' => new JwksKeySetProvider(
                    http: $app->make(Factory::class),
                    cache: $app->make('cache.store'),
                    uri: (string) $config->get('jwt.jwks.uri'),
                    cacheTtlSeconds: (int) $config->get('jwt.jwks.cache_ttl_seconds'),
                    minRefreshIntervalSeconds: (int) $config->get('jwt.jwks.min_refresh_interval_seconds'),
                    timeoutSeconds: (int) $config->get('jwt.jwks.timeout_seconds'),
                ),
                'local' => new StaticKeySetProvider(
                    kid: (string) $config->get('jwt.local.kid'),
                    publicKeyPem: $this->readLocalPublicKey((string) $config->get('jwt.local.public_key_path')),
                ),
                default => throw new InvalidArgumentException('AUTH_JWT_MODE deve ser "jwks" ou "local".'),
            };
        });

        $this->app->bind(JwtVerifier::class, function (Application $app): JwtVerifier {
            $config = $app->make(Config::class);

            return new JwtVerifier(
                keys: $app->make(KeySetProvider::class),
                issuer: (string) $config->get('jwt.issuer'),
                audience: (string) $config->get('jwt.audience'),
                leewaySeconds: (int) $config->get('jwt.leeway_seconds'),
                tenantClaim: (string) $config->get('jwt.claims.tenant'),
            );
        });
    }

    public function boot(): void
    {
        // O guard só carrega o usuário que o middleware AuthenticateJwt definiu.
        Auth::viaRequest('jwt', fn (): null => null);
    }

    private function readLocalPublicKey(string $path): string
    {
        $pem = $path !== '' && is_readable($path) ? file_get_contents($path) : false;

        if ($pem === false || $pem === '') {
            throw new InvalidArgumentException('AUTH_JWT_LOCAL_PUBLIC_KEY_PATH não aponta para uma chave legível.');
        }

        return $pem;
    }
}
