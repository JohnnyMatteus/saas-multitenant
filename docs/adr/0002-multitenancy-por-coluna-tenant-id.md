# ADR 0002: Multitenancy por coluna `tenant_id` com global scope e falha fechada

- Status: Aceito
- Data: 2026-11-21

## Contexto

Cada estúdio é um tenant. Vazamento de dados entre estúdios (lista de alunos, horários, reservas) é o pior incidente possível do produto. Ao mesmo tempo, os tenants são muitos e pequenos, e o custo por tenant precisa ser baixo para o plano de entrada fazer sentido.

As três estratégias clássicas:

| Critério | Banco por tenant | Schema por tenant | Coluna `tenant_id` (escolhida) |
| --- | --- | --- | --- |
| Isolamento | Máximo (conexão separada) | Alto (schema separado; no MySQL, schema = database) | Lógico: depende do código e do banco |
| Custo por tenant | Alto (instância/DB, backup, conexões) | Médio (centenas de schemas, migrations N vezes) | Quase zero |
| Migrations | N execuções, risco de drift entre tenants | N execuções | Uma execução |
| Onboarding de tenant | Provisionar banco | Criar schema e migrar | Inserir uma linha |
| Relatórios entre tenants | Difícil | Difícil | Uma query |
| Vizinho barulhento | Isolado | Parcial | Compartilhado (mitigar com índices e limites) |
| Restaurar um único tenant | Simples | Simples | Exige export filtrado |
| Pool de conexões | Uma por tenant | Troca de `USE` por request | Um pool |

## Decisão

Coluna `tenant_id` em toda tabela de dados de estúdio, com quatro camadas de defesa:

1. **Resolução do tenant só pela claim assinada do JWT** (`tenant`, configurável). Nunca por header, subdomínio ou parâmetro.
2. **Global scope `TenantScope`** (trait `BelongsToTenant`) em todo modelo multitenant. Sem tenant no contexto, a consulta lança `TenantContextMissing`: **falha fechada**, nunca "sem filtro".
3. **Guardas de escrita**: `creating` preenche `tenant_id` do contexto e recusa tenant diferente; `updating` recusa mudar `tenant_id`.
4. **Banco**: FKs compostas `(fk_id, tenant_id) -> (id, tenant_id)` garantem que reserva, aula, série e usuário referenciados são do mesmo tenant, mesmo se o código errar.

Policies também comparam `tenant_id` (defesa em profundidade). Recurso de outro tenant responde **404**, não 403, para não confirmar existência.

Código de sistema (seeders, factories, jobs) usa `TenantContext::runAs($tenant, fn)` ou, quando precisa atravessar tenants, `Model::withoutTenancy()`, que é explícito e fácil de achar em revisão.

## Consequências

- Onboarding barato e uma única migration; consultas e relatórios simples.
- O isolamento depende de disciplina; por isso há testes de isolamento pela API e pelo modelo, a falha fechada e as FKs compostas.
- Toda tabela nova precisa do trait e de `tenant_id` nas FKs. O checklist do PR cobra isso.
- Índices começam por `tenant_id` quando a consulta filtra por tenant.
- Restaurar um tenant isolado exige export filtrado por `tenant_id` (registrado em próximos passos).
- Um estúdio enterprise que exija banco dedicado pode ser atendido depois com resolução de conexão por tenant sem mudar os modelos (o scope continua valendo).

## Alternativas consideradas

- **Banco por tenant**: descartado pelo custo e pela operação de N migrations; reservado para um eventual plano enterprise.
- **Schema por tenant**: no MySQL schema e database são a mesma coisa, então herda boa parte dos custos do banco por tenant.
- **Row Level Security**: o MySQL não tem RLS nativo (o PostgreSQL tem). Views por tenant ou `DEFINER` resolveriam parcialmente com muito mais complexidade.
- **Scope sem falha fechada** (sem tenant = sem filtro): é o padrão de vários pacotes e o modo mais comum de vazamento; descartado.
