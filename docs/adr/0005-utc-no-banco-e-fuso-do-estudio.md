# ADR 0005: Instantes em UTC no banco, regras no fuso do estúdio e aulas materializadas

- Status: Aceito
- Data: 2026-11-21

## Contexto

"Terças e quintas às 07:00" é uma regra no relógio de parede do estúdio. Estúdios estão em fusos diferentes (São Paulo, Manaus) e, para clientes fora do Brasil, existe horário de verão. Reservas, sobreposição e janela de cancelamento comparam instantes. Aulas recorrentes precisam ter capacidade e reservas por ocorrência.

## Decisão

- Cada tenant tem um fuso IANA (`tenants.timezone`).
- O banco guarda **instantes em UTC** (`starts_at`, `ends_at`, `queued_at`); a conexão MySQL usa `time_zone = '+00:00'` e a aplicação, `UTC`.
- `WeeklyRecurrence` (domínio, sem banco) converte data a data: monta "data local + horário local" no fuso do estúdio e só então converte para UTC. Assim 07:00 continua 07:00 local depois de uma mudança de horário de verão. Horário inexistente (salto do relógio) avança para o primeiro instante válido.
- Aulas recorrentes são **materializadas** em `class_sessions` na criação da série, com horizonte máximo de 183 dias.
- A API devolve os dois: `starts_at` (UTC, contrato estável) e `starts_at_local` (com offset do estúdio). Filtros `from`/`to` são datas locais do estúdio.
- `Date::use(CarbonImmutable::class)`: datas imutáveis em toda a aplicação.

## Consequências

- Comparações de instante (sobreposição, janela, "já começou") são triviais e corretas.
- Cada ocorrência pode ter capacidade, instrutor e reservas próprias; cancelar ou ajustar uma ocorrência não afeta as outras.
- Materializar exige um job para estender o horizonte de séries longas (próximos passos) e uma regra para propagar edições da série às ocorrências futuras.
- Mudança de regra de fuso pelo governo (base tz do PHP) afeta só ocorrências geradas depois; as já geradas mantêm o instante. Aceitável e documentado.

## Alternativas consideradas

- **Guardar horário local no banco**: comparações entre fusos e no horário de verão ficam ambíguas.
- **Expandir a recorrência em tempo de leitura (RRULE)**: sem linhas não há onde pendurar capacidade e reservas por ocorrência; consultas de sobreposição ficam caras.
- **`TIMESTAMP` do MySQL**: converte pelo fuso da sessão e tem limite em 2038; `DATETIME` em UTC explícito é mais previsível.
