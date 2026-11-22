# ADR 0008: Pacotes de aulas e eventos do processador de pagamentos

- Status: Aceito (parcial: política de créditos pendente do autor)
- Data: 2026-11-22

## Contexto

O design doc previa planos e pacotes de aulas integrados ao processador de pagamentos do portfólio (`payments-events-go`). O contrato do processador é só de eventos: recebe o webhook do provedor, grava no ledger e publica `ledger.entry_recorded` v1 (exchange `payments.events`, routing key `ledger.entry_recorded`) com `event_id`, `transaction_id`, `payment_id`, `direction` (`credit` | `debit`), `amount_cents`, `currency`, `occurred_at`. **Não há API para criar cobrança nem o evento traz estúdio ou pedido**: a única ligação possível é o `payment_id`.

## Decisão

Novo módulo `Billing` (depende de Shared, Tenancy, Identity, Observability).

- **Catálogo por estúdio** (`lesson_packages`): nome, número de aulas, preço em centavos, moeda fixa BRL, ativo. Só o admin cria e edita; o número de aulas não muda depois de criado.
- **Pedido** (`package_orders`): o aluno escolhe um pacote ativo; o pedido copia número de aulas, valor e moeda (mudar o catálogo não altera pedidos) e recebe um `payment_id` próprio (`pay_<ulid>`), único na instalação, que é a referência levada ao processador. Estado `pending` ou `paid`.
- **Porta de eventos** `PaymentEventConsumer`: adaptador real `AmqpPaymentEventConsumer` (php-amqplib) com fila quorum, `x-delivery-limit` e DLQ própria, ack manual só depois do handler; mensagem fora do contrato (`InvalidPaymentMessage`) vai direto para a DLQ, falha transitória volta à fila. Nos testes, um dublê em memória reutiliza a mesma decisão de ack/DLQ (`process`), sem rede.
- **Falhas**: contrato inválido (`InvalidPaymentMessage`) vai direto para a DLQ. Falha de infraestrutura (banco fora, conexão perdida, deadlock) **não devolve a mensagem ao broker**: ela fica sem ack e o consumidor espera com backoff (1, 2, 4... até 30 s) e tenta de novo, sem limite, para que uma queda do banco não gaste o `x-delivery-limit` de toda a fila em segundos. Qualquer outra exceção tem 3 tentativas com backoff e depois DLQ (defeito determinístico não pode travar a fila). A DLQ (`<fila>.dlq`) é reservada ao que não se resolve sozinho.
- **Reprocessar a DLQ**: `php artisan billing:requeue-dead-letters --limit=100` devolve as mensagens à fila do consumidor (pela exchange padrão, para não reentregá-las a outros consumidores do processador), só tirando da DLQ depois do confirm do broker. Publica com `mandatory` e registra handler de `basic.nack` e listener de `basic.return`, porque `wait_for_pending_acks` não lança em nenhum dos dois: recusa ou fila inexistente interrompem o comando antes do ack e a mensagem continua na DLQ. Corrija a causa antes; uma mensagem ainda inválida volta para a DLQ. Não é automático: a DLQ cheia é um sinal para alguém olhar.
- **Idempotência**: `payment_events.event_id` é `UNIQUE`; o pedido é travado (`lockForUpdate`) e o evento é consultado antes de gravar, com a unique como última barreira (entrega paralela).
- **Tenant**: o evento chega sem tenant; o pedido com aquele `payment_id` o revela (única consulta sem escopo do módulo, justificada no código) e o resto roda em `TenantContext::runAs`.
- **Efeito do evento**: crédito com valor e moeda iguais aos do pedido o marca como `paid`; qualquer divergência, duplicata de pagamento ou estorno só é registrado em `payment_events` com o resultado. Evento de `payment_id` desconhecido é confirmado e logado, sem efeito.
- Fila e consumidor atrás do perfil `payments` do compose, com `PAYMENTS_AMQP_URL`.

## Não decidido (pendente do autor; nada disto é implementado)

**Eventos fora de ordem e estorno.** O broker não garante ordem (ADR 0003 do processador). Um débito (estorno) que chegue **antes** do crédito do mesmo pagamento é registrado como `refund_recorded` e o crédito que vier depois ainda marca o pedido como `paid`. Hoje isso é inofensivo, porque `paid` não concede nada; quando a concessão de créditos entrar, esse caso precisa ser coberto pela política de estorno (por exemplo, comparar o saldo líquido do pagamento em vez do último evento), senão vira crédito indevido.

Concessão e saldo de créditos, quando e como consumir (na confirmação da reserva, na promoção da fila), devolução no cancelamento, validade/expiração (implicação legal de crédito pré-pago), efeito de estorno com créditos já usados, planos recorrentes. Um pedido `paid` ainda **não** dá crédito para reservar.

## Consequências

- Não foi exercitado contra o processador nem contra um RabbitMQ real; o contrato é o do design doc do processador, replicado em `PaymentNotification` e testado por mensagens sintéticas.
- Sem API de cobrança no processador, quem leva o `payment_id` ao provedor fica fora deste repositório.
- `php-amqplib` é LGPL-2.1-or-later: uso como biblioteca via Composer, sem modificação.

## Alternativas consideradas

- **Queue do Laravel para AMQP**: esconde ack e DLQ; aqui eles são o ponto do contrato.
- **Consultar o processador por HTTP**: ele não expõe consulta de pagamento por referência do lojista.
- **Conceder créditos já**: é política de dinheiro, não decidida.
