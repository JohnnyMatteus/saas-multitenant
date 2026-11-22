<?php

declare(strict_types=1);

namespace App\Modules\Observability\Tracing;

use App\Modules\Shared\Exceptions\DomainError;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

/**
 * Registra uma exceção no span sem exportar dado pessoal. `recordException()` do SDK leva
 * a mensagem e o stack trace, e a mensagem de uma QueryException do Laravel inclui os
 * bindings do SQL (e-mail, nome, telefone). Aqui só o tipo da classe sai.
 *
 * Recusa de regra de negócio esperada (aula cheia, já reservado, janela encerrada) não é
 * falha: vira atributo `domain_error.type`. O status de erro fica para o que é inesperado.
 */
final class SpanFailure
{
    public static function record(SpanInterface $span, Throwable $e): void
    {
        $span->addEvent('exception', ['exception.type' => $e::class]);

        if ($e instanceof DomainError) {
            $span->setAttribute('domain_error.type', $e->type());

            return;
        }

        $span->setStatus(StatusCode::STATUS_ERROR);
    }
}
