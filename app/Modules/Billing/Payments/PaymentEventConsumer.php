<?php

declare(strict_types=1);

namespace App\Modules\Billing\Payments;

/**
 * Porta de entrada dos eventos do processador de pagamentos. A implementação real fala
 * AMQP; os testes usam um dublê em memória com a mesma semântica de ack e DLQ.
 */
interface PaymentEventConsumer
{
    /**
     * Consome até o contexto terminar. O handler recebe o corpo da mensagem: se retornar,
     * a mensagem é confirmada; `InvalidPaymentMessage` a manda para a DLQ sem reentrega;
     * qualquer outra exceção a devolve para nova tentativa.
     *
     * @param  callable(string): void  $handler
     */
    public function run(callable $handler): void;
}
