# ADR 0007: Spans com OpenTelemetry SDK e exportação OTLP/HTTP

- Status: Aceito
- Data: 2026-11-22

## Contexto

A fundação só propagava o `traceparent` e gravava o `trace_id` nos logs. O gateway (Kong) já exporta spans para o Jaeger; sem spans da API, o trace termina na borda e o tempo gasto na reserva (locks, transação) fica invisível.

## Decisão

- Pacotes `open-telemetry/sdk` e `open-telemetry/exporter-otlp`, sem extensão PHP nem gRPC.
- `TracerProviderFactory` monta o provider a partir de `config/tracing.php`: **sem `OTEL_EXPORTER_OTLP_ENDPOINT` o tracer é no-op** (nada sai, nada quebra); com endpoint, `BatchSpanProcessor` + exportador OTLP/HTTP em JSON (`<endpoint>/v1/traces`), amostragem `ParentBased(TraceIdRatioBased)`.
- `ObserveHttpRequests` abre o span SERVER com o `traceparent` recebido como pai. O `trace_id` dos logs e do header `X-Trace-Id` passa a ser o do span, então logs, métricas e trace casam.
- `Tracing::span()` abre spans INTERNAL nos casos de uso (`scheduling.book_session`, `scheduling.cancel_booking`), registra a exceção e a relança.
- Spans levam ids de recurso e `tenant.id`; nunca e-mail, nome ou telefone.
- O lote é exportado em `terminating` (depois da resposta enviada); timeout de 2 s e uma retentativa, e falha de exportação não afeta a requisição.
- Jaeger no perfil `observability` do compose (`make up-all`).

## Consequências

- Exportação em lote por requisição, no fim do ciclo: simples e correto para FrankenPHP sem worker mode; em worker mode a flush por requisição continua válida.
- Trabalhos na fila não continuam o trace da requisição (sem propagação de contexto no job): fica como evolução.
- Não exercitado contra um Jaeger real nesta rodada; o teste cobre o corpo OTLP/JSON enviado a um cliente HTTP simulado.
- O Jaeger do `platform-edge` está só na rede `edge`; para a API exportar para ele, ele precisa entrar na `portfolio-net` (mudança do outro repositório).

## Alternativas consideradas

- **Extensão `opentelemetry` com auto-instrumentação**: exige instalar extensão na imagem e cobre pouco do domínio.
- **Exportar via gRPC**: exige `ext-grpc`, pesada na imagem.
- **Collector no meio**: melhor em produção; para dev, enviar direto ao Jaeger (que já fala OTLP) basta.
