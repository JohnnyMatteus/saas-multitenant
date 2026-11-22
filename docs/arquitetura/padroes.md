# Padrões de projeto e SOLID no código

Cada item aponta para o arquivo onde o padrão existe de fato.

| Padrão | Onde | Por que está lá |
| --- | --- | --- |
| Porta e adaptador (hexagonal) | `Billing/Payments/PaymentEventConsumer.php` (porta), `AmqpPaymentEventConsumer.php` (adaptador), `tests/Support/InMemoryPaymentEventConsumer.php` (dublê) | O caso de uso não conhece o broker; o dublê reaproveita `process()` do adaptador real, então teste e produção decidem ack e DLQ do mesmo jeito. |
| Inversão de dependência | Casos de uso recebem `ConnectionInterface`, `LoggerInterface`, `TenantContext` pelo construtor (`Billing/Application/RecordPaymentNotification.php`) | Testável sem framework. |
| Decorator | `Observability/Tracing/BackoffSpanExporter.php` envolve o exportador OTLP | Acrescenta janela de backoff sem alterar o exportador do SDK. |
| Factory | `Observability/Tracing/TracerProviderFactory.php` | Escolhe SDK ou no-op pela configuração e aceita cliente HTTP injetado (teste). |
| Null Object | `NoopTracerProvider` quando não há endpoint | O código instrumentado nunca checa "tracing ligado?". |
| Global scope como filtro obrigatório | `Tenancy/Scopes/TenantScope.php`, trait `BelongsToTenant` | Isolamento por padrão, com falha fechada. |
| Consumidor idempotente | `Billing/Application/RecordPaymentNotification.php` (unique em `event_id` + lock do pedido) | Entrega at-least-once do broker sem efeito duplicado. |
| Value object validado na borda | `Billing/Domain/PaymentNotification.php`, `Scheduling/Domain/WeeklyRecurrence.php` | A regra de formato vive no domínio, antes de tocar no banco. |
| Caso de uso por classe | `Scheduling/Application/*`, `Billing/Application/*` | Uma razão para mudar por classe (SRP); transações e locks ficam aqui, não no controller. |
| Estratégia por enum | `Scheduling/Domain/SeriesEditScope.php`, `Billing/Domain/PaymentOutcome.php` | Variações explícitas e exaustivas. |
| Streaming em vez de acumular | `Portability/Export/TenantExporter.php` (`lazyById`) | Memória constante para estúdios grandes. |

SOLID, onde aparece de forma verificável:

- **S**: `ExportBackoff` só decide janela e aviso; `SpanFailure` só decide o que um span registra; `FailureClassifier` só separa infraestrutura de defeito.
- **O**: novo tipo de evento de pagamento entra em `PaymentOutcome` e `decide()`, sem mexer no consumidor.
- **L**: `InMemoryPaymentEventConsumer` e `AmqpPaymentEventConsumer` são intercambiáveis para o comando `billing:consume-payments`.
- **I**: a porta de pagamentos tem um único método (`run`).
- **D**: ver "Inversão de dependência"; as fronteiras entre módulos são checadas por `tests/Unit/Architecture/ModuleBoundariesTest.php`.
