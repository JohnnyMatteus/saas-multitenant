# ADR 0006: Infraestrutura local com Docker Compose, perfis e serviços emulados

- Status: Aceito
- Data: 2026-11-21

## Contexto

A fundação precisa subir com um comando e reproduzir os serviços que o domínio usa de verdade: identidade (Keycloak), banco (MySQL), fila e cache (para e-mails fora da transação), e-mail transacional (aviso de promoção da lista de espera) e armazenamento de objetos (logo do estúdio, white label). O mesmo código precisa rodar na AWS trocando só variáveis.

## Decisão

`docker-compose.yml` com imagens de versão fixada, `healthcheck` em todo serviço de infraestrutura, `depends_on` com `service_healthy` (ou `service_completed_successfully` para a migration), volumes nomeados, rede nomeada `studio-net` e perfis:

| Perfil | Serviços | Uso no domínio |
| --- | --- | --- |
| `core` | `app` (FrankenPHP), `worker` (fila), `migrate` (one-off), `mysql` 8.4, `redis` 7.4, `keycloak` 26.4 (realm exportado), `mailpit`, `localstack` (S3) | API completa |
| `observability` | `prometheus` raspando `/metrics` | Métricas RED e de agenda |
| `tools` | `adminer` | Inspeção do banco |

- **Redis**: fila (`QUEUE_CONNECTION=redis`) para o listener que envia o e-mail de promoção, e cache (JWKS). Fora do Docker e nos testes: `sync` e `array`/`file`.
- **Mailpit**: SMTP de desenvolvimento; e-mails visíveis em `http://localhost:8025`.
- **LocalStack (só S3)**: bucket `studio-media` criado por `docker/localstack/01-buckets.sh` em `/etc/localstack/init/ready.d`, com acesso público bloqueado. O código usa o disco `s3` do Laravel por meio do adaptador `LogoStorage`; `AWS_ENDPOINT` aponta para o LocalStack em dev e é removido na AWS real.
- Rede compartilhada opcional `portfolio-net` (externa) via `docker-compose.portfolio.yml` e `make network`, para o gateway do ecossistema alcançar a API.
- `Makefile` com alvos `up`, `down`, `logs`, `ps`, `seed`, `test`, `test-integration`, `clean` e auxiliares (`lint`, `stan`, `check`, `token`).
- Variáveis em `.env.example`; senhas sempre `dev-only-change-me`.

## Consequências

- `make up` sobe tudo pronto (health checks garantem a ordem); testes só precisam do MySQL.
- Mais contêineres na máquina do desenvolvedor; os perfis deixam extras desligados por padrão.
- O worker é obrigatório para e-mails no Docker; se ele cair, a promoção acontece (está no banco) e o e-mail fica na fila até ele voltar.
- Testes nunca falam com Redis, SMTP ou S3: usam `Queue::fake`, `Mail::fake` e `Storage::fake`, com o mesmo contrato.

## Alternativas consideradas

- **MinIO para o S3**: mais leve que o LocalStack, porém as tags da imagem oficial no Docker Hub não estavam disponíveis no momento da decisão, e o LocalStack deixa o caminho aberto para SES/SQS sem trocar de ferramenta.
- **SES no LocalStack em vez de Mailpit**: Mailpit mostra o e-mail renderizado numa UI, que é o que se quer em dev; SES real entra pela configuração de mail na AWS.
- **Fila no banco (driver `database`)**: dispensaria o Redis, mas aumenta escrita no MySQL justamente no pico de reservas.
- **Sem perfis**: tudo sobe sempre; mais lento e com conflito de portas com outros projetos.
