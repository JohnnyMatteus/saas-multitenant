<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http;

use App\Modules\Shared\Exceptions\DomainError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Traduz exceções em respostas application/problem+json (RFC 9457).
 *
 * Retorna null para exceções desconhecidas, deixando o handler padrão do
 * Laravel produzir o 500 (sem detalhes internos fora de APP_DEBUG).
 */
final class ProblemDetailsRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        return match (true) {
            $e instanceof DomainError => $this->problem($e->status(), $e->type(), $e->getMessage()),
            $e instanceof AuthenticationException => $this->problem(401, 'unauthenticated', $e->getMessage())
                ->withHeaders(['WWW-Authenticate' => 'Bearer']),
            // O Laravel já converteu AuthorizationException e ModelNotFoundException
            // nas exceções HTTP equivalentes antes de chegar aqui.
            $e instanceof AccessDeniedHttpException => $this->problem(403, 'forbidden', 'Ação não permitida para este usuário.'),
            $e instanceof NotFoundHttpException => $this->problem(404, 'not_found', 'Recurso não encontrado.'),
            $e instanceof ValidationException => $this->problem(422, 'validation_failed', 'Dados inválidos.', [
                'errors' => $e->errors(),
            ]),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500 => $this->problem(
                $e->getStatusCode(),
                'http_error',
                $e->getMessage() !== '' ? $e->getMessage() : 'Requisição inválida.',
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function problem(int $status, string $type, string $detail, array $extra = []): JsonResponse
    {
        return new JsonResponse(
            ['type' => $type, 'title' => $this->title($status), 'status' => $status, 'detail' => $detail] + $extra,
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }

    private function title(int $status): string
    {
        return match ($status) {
            401 => 'Não autenticado',
            403 => 'Proibido',
            404 => 'Não encontrado',
            409 => 'Conflito',
            422 => 'Não processável',
            503 => 'Serviço indisponível',
            default => 'Erro na requisição',
        };
    }
}
