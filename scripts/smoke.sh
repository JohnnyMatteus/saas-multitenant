#!/usr/bin/env bash
# Smoke da stack completa (docker compose --profile core): exercita o que sobe de verdade, pela porta
# de entrada de cada caminho. Usa só dados fictícios do realm e do seeder; nada vai para fora da máquina.
# Cada verificação é contada e o script falha se rodarem menos que o mínimo esperado (passo pulado não vira verde).
set -euo pipefail

API="${API_URL:-http://localhost:8000}"
WEB="${WEB_URL:-http://localhost:5173}"
KEYCLOAK="${KEYCLOAK_URL:-http://localhost:8080}"
MAILPIT="${MAILPIT_URL:-http://localhost:8025}"
PASSWORD="dev-only-change-me"
MIN_CHECKS=40

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
checks=0
failures=0

pass() { checks=$((checks + 1)); printf 'ok    %s\n' "$1"; }
fail() { checks=$((checks + 1)); failures=$((failures + 1)); printf 'FALHA %s\n' "$1" >&2; }
check() { # descricao esperado obtido
  if [ "$2" = "$3" ]; then pass "$1"; else fail "$1 (esperado '$2', obtido '$3')"; fi
}
contains() { # descricao arquivo trecho
  if grep -Fq -- "$3" "$2"; then pass "$1"; else fail "$1 (trecho '$3' ausente)"; fi
}
lacks() { # descricao arquivo trecho
  if grep -Fq -- "$3" "$2"; then fail "$1 (trecho '$3' presente)"; else pass "$1"; fi
}
die() { printf 'ERRO  %s\n' "$1" >&2; exit 1; }

token() { # usuario -> access token
  local t
  t="$(curl -fsS -X POST "$KEYCLOAK/realms/estudios/protocol/openid-connect/token" \
    -d grant_type=password -d client_id=studio-dev-cli -d "username=$1" -d "password=$PASSWORD" | jq -r '.access_token // empty')"
  [ -n "$t" ] || die "sem token para $1: o Keycloak importou o realm?"
  printf '%s' "$t"
}

# request METODO URL TOKEN [JSON]: grava o corpo em $work/body e devolve o status HTTP.
request() {
  local method="$1" url="$2" tok="$3" data="${4:-}"
  local args=(-s -o "$work/body" -w '%{http_code}' -X "$method" "$url" -H 'Accept: application/json')
  [ -z "$tok" ] || args+=(-H "Authorization: Bearer $tok")
  [ -z "$data" ] || args+=(-H 'Content-Type: application/json' -d "$data")
  curl "${args[@]}"
}

echo '== tokens reais do Keycloak'
ADMIN="$(token admin.lotus@example.com)"
ALUNA="$(token aluna.lotus@example.com)"
INSTRUTORA="$(token instrutor.lotus@example.com)"
ADMIN_OUTRO="$(token admin.equilibrio@example.com)"
ALUNO_OUTRO="$(token aluno.equilibrio@example.com)"

echo '== autenticacao'
check 'sem token: 401' 401 "$(request GET "$API/api/v1/me" '')"
check 'token inventado: 401' 401 "$(request GET "$API/api/v1/me" 'abc.def.ghi')"
# Token com payload adulterado (assinatura deixa de bater): troca o tenant na claim.
# Token com payload adulterado (a assinatura deixa de bater): troca o tenant na claim.
forged="$(python3 - "$ALUNA" <<'PY'
import base64, sys
head, payload, sig = sys.argv[1].split('.')
raw = base64.urlsafe_b64decode(payload + '=' * (-len(payload) % 4)).replace(b'estudio-lotus', b'pilates-equilibrio')
print('.'.join([head, base64.urlsafe_b64encode(raw).decode().rstrip('='), sig]))
PY
)"
check 'token com tenant adulterado: 401' 401 "$(request GET "$API/api/v1/me" "$forged")"
check 'me da aluna: 200' 200 "$(request GET "$API/api/v1/me" "$ALUNA")"
check 'aluna: estudio da claim' estudio-lotus "$(jq -r '.data.studio.slug' "$work/body")"
check 'aluna: papel' student "$(jq -r '.data.role' "$work/body")"
check 'me do admin do outro estudio' pilates-equilibrio "$(request GET "$API/api/v1/me" "$ADMIN_OUTRO" >/dev/null; jq -r '.data.studio.slug' "$work/body")"
check 'header de tenant forjado e ignorado' estudio-lotus "$(curl -s -H "Authorization: Bearer $ALUNA" -H 'X-Tenant: pilates-equilibrio' -H 'X-Tenant-Id: pilates-equilibrio' "$API/api/v1/me" | jq -r '.data.studio.slug')"

echo '== aula recorrente, reserva e cancelamento (admin -> aluna)'
DATE="$(TZ=America/Sao_Paulo date -d '+3 days' +%F)"
WEEKDAY="$(TZ=America/Sao_Paulo date -d '+3 days' +%u)"
check 'aluna nao cria aula: 403' 403 "$(request POST "$API/api/v1/class-series" "$ALUNA" '{}')"
check 'lista de instrutores' 200 "$(request GET "$API/api/v1/instructors" "$ADMIN")"
INSTRUCTOR_ID="$(jq -r '[.data[] | select(.role=="instructor")][0].id // empty' "$work/body")"
[ -n "$INSTRUCTOR_ID" ] || die 'sem instrutor seedado no estudio'
check 'admin cria a aula (capacidade 1)' 201 "$(request POST "$API/api/v1/class-series" "$ADMIN" \
  "{\"title\":\"Smoke Hatha\",\"instructor_id\":\"$INSTRUCTOR_ID\",\"weekdays\":[$WEEKDAY],\"start_time\":\"07:00\",\"duration_minutes\":60,\"capacity\":1,\"starts_on\":\"$DATE\",\"ends_on\":\"$DATE\"}")"
check 'grade da aluna' 200 "$(request GET "$API/api/v1/sessions?from=$DATE&to=$DATE" "$ALUNA")"
SESSION_ID="$(jq -r '[.data[] | select(.title=="Smoke Hatha")][0].id // empty' "$work/body")"
[ -n "$SESSION_ID" ] || die 'a aula criada nao apareceu na grade'
check 'aula com 1 vaga' 1 "$(jq -r '[.data[] | select(.title=="Smoke Hatha")][0].available_spots' "$work/body")"
check 'aluna reserva: 201' 201 "$(request POST "$API/api/v1/sessions/$SESSION_ID/bookings" "$ALUNA")"
check 'reserva confirmada' confirmed "$(jq -r '.data.status' "$work/body")"
BOOKING_ID="$(jq -r '.data.id' "$work/body")"
check 'reserva repetida: 409' 409 "$(request POST "$API/api/v1/sessions/$SESSION_ID/bookings" "$ALUNA")"
check 'admin ve a lista de presenca' 200 "$(request GET "$API/api/v1/sessions/$SESSION_ID/bookings" "$ADMIN")"
check 'lista com a aluna' 1 "$(jq -r '[.data[] | select(.status=="confirmed")] | length' "$work/body")"
check 'aluna nao ve a lista de presenca: 403' 403 "$(request GET "$API/api/v1/sessions/$SESSION_ID/bookings" "$ALUNA")"
check 'instrutor da aula ve a lista de presenca' 200 "$(request GET "$API/api/v1/sessions/$SESSION_ID/bookings" "$INSTRUTORA")"

echo '== isolamento entre estudios (404, nunca 403 nem dado)'
check 'outro estudio nao le a aula' 404 "$(request GET "$API/api/v1/sessions/$SESSION_ID" "$ALUNO_OUTRO")"
check 'outro estudio nao reserva' 404 "$(request POST "$API/api/v1/sessions/$SESSION_ID/bookings" "$ALUNO_OUTRO")"
check 'outro admin nao ve a lista' 404 "$(request GET "$API/api/v1/sessions/$SESSION_ID/bookings" "$ADMIN_OUTRO")"
check 'outro estudio nao cancela a reserva' 404 "$(request POST "$API/api/v1/bookings/$BOOKING_ID/cancel" "$ALUNO_OUTRO")"
check 'grade do outro estudio nao tem a aula' 0 "$(request GET "$API/api/v1/sessions?from=$DATE&to=$DATE" "$ALUNO_OUTRO" >/dev/null; jq -r '[.data[] | select(.title=="Smoke Hatha")] | length' "$work/body")"

echo '== pacotes e pedidos'
check 'aluna nao cria pacote: 403' 403 "$(request POST "$API/api/v1/lesson-packages" "$ALUNA" '{"name":"x","lesson_count":8,"price_cents":100}')"
check 'admin cria pacote' 201 "$(request POST "$API/api/v1/lesson-packages" "$ADMIN" '{"name":"Smoke 8 aulas","lesson_count":8,"price_cents":32000}')"
PACKAGE_ID="$(jq -r '.data.id' "$work/body")"
check 'aluna ve o pacote' 200 "$(request GET "$API/api/v1/lesson-packages" "$ALUNA")"
check 'aluna faz o pedido' 201 "$(request POST "$API/api/v1/package-orders" "$ALUNA" "{\"package_id\":\"$PACKAGE_ID\"}")"
check 'pedido pendente' pending "$(jq -r '.data.status' "$work/body")"
check 'pedido com referencia de pagamento' 1 "$(jq -r '[.data.payment_id | select(startswith("pay_"))] | length' "$work/body")"
check 'outro estudio nao compra o pacote: 404' 404 "$(request POST "$API/api/v1/package-orders" "$ALUNO_OUTRO" "{\"package_id\":\"$PACKAGE_ID\"}")"

echo '== exportacao do estudio'
check 'aluna nao exporta: 403' 403 "$(request GET "$API/api/v1/studio/export" "$ALUNA")"
check 'admin exporta: 200' 200 "$(request GET "$API/api/v1/studio/export" "$ADMIN")"
check 'documento e JSON do proprio estudio' estudio-lotus "$(jq -r '.tenant.slug' "$work/body")"
contains 'export inclui a aula criada' "$work/body" 'Smoke Hatha'
lacks 'export sem nada do outro estudio' "$work/body" 'pilates-equilibrio'
lacks 'export sem usuario do outro estudio' "$work/body" 'admin.equilibrio@example.com'

echo '== front (nginx) como porta de entrada'
check 'healthz do front' 200 "$(curl -s -o /dev/null -w '%{http_code}' "$WEB/healthz")"
check 'SPA servida' 200 "$(curl -s -o "$work/index.html" -w '%{http_code}' "$WEB/")"
contains 'SPA tem o ponto de montagem' "$work/index.html" 'id="app"'
check 'rota do vue-router cai no index' 200 "$(curl -s -o /dev/null -w '%{http_code}' "$WEB/app/agenda")"
check 'API pelo proxy do front' 200 "$(request GET "$WEB/api/v1/me" "$ALUNA")"
check 'proxy sem token: 401' 401 "$(request GET "$WEB/api/v1/me" '')"
check 'front nao expoe /metrics' 404 "$(curl -s -o /dev/null -w '%{http_code}' "$WEB/metrics")"
check 'front nao expoe a rota de integracao' 404 "$(curl -s -o /dev/null -w '%{http_code}' -X POST "$WEB/api/v1/integrations/whatsapp/links")"
check 'front nao expoe integracao codificada' 404 "$(curl -s -o /dev/null -w '%{http_code}' -X POST --path-as-is "$WEB/api/v1/%69ntegrations/whatsapp/links")"
check 'CSP no front' 1 "$(curl -sI "$WEB/" | grep -ci '^content-security-policy:')"
check 'CSP tambem nos assets' 1 "$(curl -s "$WEB/" | grep -o '/assets/[^"]*\.js' | head -1 | xargs -I{} curl -sI "$WEB{}" | grep -ci '^content-security-policy:')"

echo '== cancelamento, fila de e-mail e metricas'
check 'aluna cancela: 200' 200 "$(request POST "$API/api/v1/bookings/$BOOKING_ID/cancel" "$ALUNA")"
check 'reserva cancelada' canceled "$(jq -r '.data.canceled.status' "$work/body")"
check 'cancelar de novo: 409' 409 "$(request POST "$API/api/v1/bookings/$BOOKING_ID/cancel" "$ALUNA")"

# O e-mail de confirmacao sai pela fila Redis e pelo worker: espera ate 60 s.
mail_count=0
for _ in $(seq 1 30); do
  mail_count="$(curl -fsS "$MAILPIT/api/v1/messages" | jq '[.messages[] | select(any(.To[]; .Address=="aluna.lotus@example.com"))] | length')"
  [ "$mail_count" -ge 1 ] && break
  sleep 2
done
check 'e-mail de confirmacao chegou ao Mailpit (fila Redis + worker)' 1 "$([ "$mail_count" -ge 1 ] && echo 1 || echo 0)"

curl -fsS "$API/metrics" -o "$work/metrics"
contains 'metrica de reservas confirmadas' "$work/metrics" 'saas_bookings_total{result="confirmed"}'
contains 'metrica HTTP por rota' "$work/metrics" 'saas_http_requests_total'
lacks 'metricas sem tenant nos rotulos' "$work/metrics" 'tenant_id='

echo
printf '%s verificacoes, %s falhas\n' "$checks" "$failures"
[ "$failures" -eq 0 ] || exit 1
[ "$checks" -ge "$MIN_CHECKS" ] || die "so $checks verificacoes rodaram (minimo $MIN_CHECKS): passo pulado?"
