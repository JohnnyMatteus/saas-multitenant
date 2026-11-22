<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MetricsAccessTest extends TestCase
{
    #[Test]
    public function metrics_are_open_when_the_token_is_not_required(): void
    {
        config(['metrics.require_token' => false]);

        $this->get('/metrics')->assertOk();
    }

    #[Test]
    public function without_a_configured_token_the_route_is_closed(): void
    {
        config(['metrics.require_token' => true, 'metrics.token' => null]);

        $this->get('/metrics')->assertForbidden();
        $this->withToken('qualquer')->get('/metrics')->assertForbidden();
    }

    #[Test]
    public function a_missing_or_wrong_token_is_rejected_and_the_right_one_accepted(): void
    {
        config(['metrics.require_token' => true, 'metrics.token' => 'scrape-token-exemplo']);

        $this->get('/metrics')->assertUnauthorized();
        $this->withToken('errado')->get('/metrics')->assertUnauthorized();
        $this->withToken('scrape-token-exemplo')->get('/metrics')->assertOk();
    }
}
