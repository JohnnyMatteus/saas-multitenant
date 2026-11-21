# ADR 0001: Monólito modular em Laravel, com módulos por contexto

- Status: Aceito
- Data: 2026-11-21

## Contexto

O produto atende estúdios pequenos (dezenas de alunos por aula, poucas centenas de aulas por semana por estúdio). A equipe imaginada é pequena e o domínio ainda está sendo descoberto: regras de agenda vão mudar muito. Precisamos de fronteiras claras entre contextos (identidade, tenancy, agenda, marca, observabilidade) sem pagar o custo operacional de vários serviços.

## Decisão

Um único deploy Laravel 13 (PHP 8.4) organizado em módulos em `app/Modules/<Contexto>`:

| Módulo | Responsabilidade | Pode depender de |
| --- | --- | --- |
| `Shared` | Erros de domínio e Problem Details | nada |
| `Tenancy` | Tenant, contexto, global scope | Shared |
| `Observability` | Logs de acesso, correlação, métricas | Shared |
| `Identity` | JWT/OIDC, papéis, usuário local | Shared, Tenancy |
| `Scheduling` | Aulas, reservas, lista de espera | Shared, Tenancy, Identity, Observability |
| `Branding` | Logo do estúdio (S3) | Shared, Tenancy, Identity |

Dentro de cada módulo, camadas explícitas: `Domain` (regras puras, sem Eloquent quando possível), `Application` (casos de uso, transações, locks), `Models` (Eloquent), `Policies`, `Http` (controllers finos, FormRequests, Resources). Cada módulo tem seu ServiceProvider e seu arquivo de rotas.

As dependências permitidas são verificadas por teste (`tests/Unit/Architecture/ModuleBoundariesTest.php`), que falha se um módulo importar outro fora da tabela.

## Consequências

- Um deploy, um banco, transações locais: a reserva e a promoção da fila acontecem numa única transação.
- Fronteiras visíveis no código e testadas; extrair um módulo para serviço no futuro é mover uma pasta e trocar chamadas diretas por mensagens.
- Laravel facilita (Eloquent, policies, filas, Storage), mas convida a misturar camadas. A disciplina fica a cargo da revisão e do teste de fronteiras, que só olha `use` de outros módulos (não detecta acoplamento via container ou strings).
- `Scheduling` conhece o `User` de `Identity`. É uma dependência consciente: o aluno e o instrutor são identidades.

## Alternativas consideradas

- **Microsserviços por contexto**: isolamento forte, mas transação distribuída entre reserva e fila, mais infraestrutura e nenhuma necessidade de escala independente hoje.
- **Laravel "padrão" (`app/Models`, `app/Http/Controllers`)**: mais familiar, mas as fronteiras somem quando o projeto cresce.
- **Hyperf**: melhor desempenho com corrotinas, porém contexto por corrotina complica o tenant global e o ecossistema de pacotes é menor. A carga prevista não justifica.
