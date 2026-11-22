# ADR 0009: Exportação de dados por estúdio

- Status: Aceito (importação e remoção de dados dependem de decisões do autor; ver abaixo)
- Data: 2026-11-22

## Contexto

O design doc previa export/import por tenant (portabilidade e restauração isolada). Exportar é leitura; importar e apagar escrevem e são irreversíveis. Só a exportação foi decidida.

## Decisão

Módulo `Portability` (depende de Shared, Tenancy, Identity, Observability, Scheduling, Billing, só para ler os modelos).

- `GET /api/v1/studio/export`: só `studio_admin`, do **próprio** estúdio (o tenant vem da claim; header e parâmetro são ignorados e há teste disso).
- Resposta em **streaming** (`StreamedResponse`) de um único documento JSON (`format_version: 1`): estúdio (slug, nome, fuso, janela), usuários, séries, aulas, reservas, pacotes, pedidos e eventos de pagamento. Linhas lidas em blocos (`lazyById`), sem carregar tabelas na memória, dentro de uma transação de leitura (retrato consistente).
- O corpo é gerado depois que o middleware de autenticação limpou o contexto; por isso o tenant é capturado e o exportador roda em `TenantContext::runAs`, e toda consulta passa pelo `TenantScope`.
- Colunas listadas uma a uma; ficam de fora o vínculo de WhatsApp (hash do telefone e códigos), o arquivo do logo e qualquer dado de outro estúdio.
- Contém dado pessoal dos alunos (nome, e-mail, `subject` do IdP). Mitigações: só admin, `Cache-Control: no-store`, nada é gravado em disco no servidor, limite de 5 por hora por usuário e estúdio, e auditoria em log (`studio_export`: tenant, usuário e contagens, sem dado pessoal) e métrica `studio_exports_total`.
- Um download interrompido gera JSON truncado, que não faz parse: não confunde com um export completo.

## Não decidido (pendente do autor; nada disto é implementado)

- **Importação**: recomendação é importar só para um estúdio novo e vazio, sem sobrescrever nem apagar nada. Falta confirmar, e definir como os usuários se religam ao IdP (`subject`) e como tratar `payment_id` e `event_id` já existentes (são únicos na instalação).
- **Remoção** de estúdio ou de aluno e **prazo de retenção** (implicação legal, LGPD).
- Usuário em vários estúdios (seleção no login): depende da decisão sobre o mecanismo no Keycloak.

## Alternativas consideradas

- **Job assíncrono com arquivo no S3**: cobre estúdios enormes, mas deixa dado pessoal em repouso e exige expiração; para o porte esperado o streaming basta.
- **CSV por tabela**: perde a estrutura; o JSON único mantém as referências entre linhas.
