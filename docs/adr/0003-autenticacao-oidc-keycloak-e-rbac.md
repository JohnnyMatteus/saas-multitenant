# ADR 0003: Autenticação OIDC (Keycloak) com validação local de JWT e RBAC por policies

- Status: Aceito
- Data: 2026-11-21

## Contexto

A API é consumida por um front-end Vue e, no ecossistema do portfólio, fica atrás de um gateway (projeto `platform-edge`). Precisamos de login sem guardar senhas, papéis por estúdio (admin, instrutor, aluno) e um modo de teste que não dependa de um IdP rodando.

## Decisão

- **Keycloak como IdP** (realm `estudios`). A API não emite tokens; só valida.
- **Validação local de access tokens RS256** com `firebase/php-jwt`:
  - recusa explícita de qualquer `alg` diferente de RS256 antes de olhar a chave (bloqueia `none` e a confusão RS256/HS256);
  - `kid` obrigatório; chaves vindas do **JWKS configurável** (`AUTH_JWT_JWKS_URI`), em cache, com uma releitura limitada por intervalo quando aparece um `kid` novo (rotação de chave sem DoS no IdP);
  - checagem de `exp`/`nbf` com folga configurável, `iss`, `aud` (`studio-api`, inserida por audience mapper), `sub` e claim de tenant;
  - motivo da rejeição vai para o log; o cliente recebe sempre um 401 genérico.
- **Modo `local`** (`AUTH_JWT_MODE=local`): chave pública PEM de um arquivo. Os testes geram o par RSA em memória a cada execução; nenhuma chave privada é versionada.
- **Papéis = realm roles** (`studio_admin`, `instructor`, `student`) em `realm_access.roles`. Se o token tiver vários, vale o de maior privilégio.
- **Provisionamento just-in-time**: o primeiro request cria o espelho local do usuário (`users`, por `tenant_id` + `sub`); requests seguintes atualizam nome, e-mail e papel. O IdP é a fonte da verdade.
- **Autorização por policies do Laravel** (`ClassSeriesPolicy`, `ClassSessionPolicy`, `BookingPolicy`, gate de marca). Regras que dependem do papel e do tempo (admin cancela dentro da janela) ficam no caso de uso.

## Consequências

- Nenhuma chamada ao IdP por request (só o JWKS em cache); a API continua de pé com o Keycloak fora do ar enquanto o cache valer.
- Revogação não é imediata: um token vale até expirar (15 min no realm de exemplo). Aceitável para agenda; introspecção fica para ações críticas futuras.
- O papel muda no Keycloak e vale no próximo token, sem sincronização.
- Um usuário que frequenta dois estúdios tem duas linhas em `users` (uma por tenant), cada uma com seu papel.

## Alternativas consideradas

- **Laravel Sanctum/Passport**: a API viraria IdP, com senhas e telas de login próprias. Contraria o objetivo de SSO e do gateway compartilhado.
- **Introspecção a cada request**: revogação imediata, mas latência e dependência forte do IdP.
- **Pacote de guard JWT pronto**: menos código, mas esconde exatamente as checagens que queremos explícitas e testadas.
- **ACL por permissão granular**: mais flexível, porém três papéis fixos cobrem o domínio atual. Fica como evolução se surgir, por exemplo, "recepcionista".
