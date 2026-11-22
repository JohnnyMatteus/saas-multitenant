# CLAUDE.md

Guia para sessões de agente neste repositório.

## Visão

API multitenant de agendamento para estúdios de yoga e pilates (domínio fictício). Núcleo: **isolamento por tenant** e **regras de agenda** (recorrência no fuso, capacidade, fila com promoção, janela de cancelamento, dupla reserva, concorrência). Leia antes de mudar algo: `docs/design-doc.md`, `docs/adr/`, `docs/roteiros/`.

## Regras inegociáveis

1. **Isolamento**: todo modelo com dados de estúdio usa `BelongsToTenant`; toda tabela nova tem `tenant_id` e FKs compostas `(x_id, tenant_id)`. Nunca leia o tenant de header/parâmetro: só da claim do JWT. `withoutTenancy()` só em código de sistema e testes, com justificativa.
2. **Falha fechada**: sem tenant no contexto, consultas lançam exceção. Não "conserte" isso removendo o scope. Jobs usam `TenantContext::runAs()` com o tenant vindo no evento.
3. **Concorrência**: reservas e cancelamentos travam na ordem aluno -> aula -> reservas (`lockForUpdate`); edição de série trava série -> aulas -> reservas e nunca é chamada de dentro de uma reserva. Não mude a ordem sem atualizar o ADR 0004 e o `BookingConcurrencyTest`.
4. **Tempo**: banco em UTC; regras no fuso do estúdio; nunca grave Carbon fora de UTC.
5. **Fronteiras de módulo**: respeite a tabela de `tests/Unit/Architecture/ModuleBoundariesTest.php`. Nova dependência entre módulos pede ADR.
6. **Domínio fictício**: nomes "Exemplo", e-mails `@example.com`, senhas só `dev-only-change-me`. Nenhum segredo real; `.env` nunca é versionado.
7. Docs em português do Brasil; identificadores em inglês. Testes nunca acessam a internet.

## Comandos

```bash
make up                 # stack completa (perfil core)
make test               # PHPUnit (sobe o MySQL do compose na porta 3307)
make test-unit          # sem banco
make lint stan          # Pint e PHPStan nível 8
make check              # o que o CI roda
make logs | ps | seed | down | clean
```

Sem Make: `docker compose --profile core up -d mysql && vendor/bin/phpunit`.

## Estrutura

```
app/Modules/
  Shared/         DomainError, ProblemDetailsRenderer
  Tenancy/        TenantContext, TenantScope, BelongsToTenant, Tenant
  Identity/       Jwt/ (verifier, JWKS), AuthenticateJwt, ProvisionUser, User, Role
  Scheduling/     Domain/ (regras puras), Application/ (casos de uso), Models, Policies, Http, Listeners, Mail
  Branding/       LogoStorage (S3), controller do logo
  Portability/    Exportação dos dados do estúdio (streaming, só admin)
  Billing/        Pacotes, pedidos (payment_id próprio), consumidor dos eventos do processador (porta + dublê)
  Observability/  ObserveHttpRequests, Metrics, /metrics
database/migrations  MySQL (FKs compostas, CHECKs)
docker/              init do MySQL, realm do Keycloak, bootstrap do LocalStack, Prometheus
tests/Unit           domínio puro, JWT, policies, fronteiras
tests/Feature        API + MySQL: isolamento, agenda, concorrência (pcntl_fork), observabilidade
docs/                design doc, ADRs, roteiros
```

## Fluxo de trabalho

1. Branch a partir da `main` (`feat/...`, `fix/...`).
2. TDD nas regras: teste unitário em `Domain/` quando a regra é pura; teste de feature quando envolve banco, papel ou tenant. Todo recurso novo ganha um teste de isolamento (outro tenant recebe 404).
3. `make check` verde antes de commitar.
4. Conventional Commits em português (`feat:`, `fix:`, `test:`, `docs:`, `ci:`, `chore:`), commits pequenos, mensagem via `git commit -F arquivo`.
5. Decisão de arquitetura nova ou alterada: ADR em `docs/adr/` e atualização do design doc e do roteiro correspondente.
