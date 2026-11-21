import { expect, test, type APIRequestContext, type APIResponse } from '@playwright/test';
import { narrar, type Chamada } from './narrar';

const API = process.env.API_URL ?? 'http://localhost:8000';
const KEYCLOAK = process.env.KEYCLOAK_URL ?? 'http://localhost:8080';
const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8025';
const PROMETHEUS = process.env.PROMETHEUS_URL ?? 'http://localhost:9090';
const SENHA = 'dev-only-change-me';
const FUSO = 'America/Sao_Paulo';

const USUARIOS = {
  admin: 'admin.lotus@example.com',
  instrutor: 'instrutor.lotus@example.com',
  alunaA: 'aluna.lotus@example.com',
  alunaB: 'aluna.beatriz.lotus@example.com',
  alunoC: 'aluno.caio.lotus@example.com',
  adminOutro: 'admin.equilibrio@example.com',
  alunoOutro: 'aluno.equilibrio@example.com',
} as const;
type Papel = keyof typeof USUARIOS;

// Data local do estúdio daqui a N dias. A aula fica a mais de 12 h (janela de
// cancelamento do Estúdio Lótus), para que a aluna possa cancelar sozinha.
function dataLocal(diasAFrente: number): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: FUSO }).format(new Date(Date.now() + diasAFrente * 86_400_000));
}
const diaIso = (data: string) => new Date(`${data}T12:00:00Z`).getUTCDay() || 7;

function decodificar(jwt: string): Record<string, any> {
  return JSON.parse(Buffer.from(jwt.split('.')[1], 'base64url').toString('utf8'));
}

async function chamada(metodo: string, url: string, resp: APIResponse): Promise<Chamada & { json: any }> {
  const texto = await resp.text();
  let corpo: unknown = texto;
  try {
    corpo = JSON.parse(texto);
  } catch {}
  return { metodo, url: url.replace(API, ''), status: resp.status(), corpo, json: corpo };
}

async function metricas(request: APIRequestContext): Promise<{ texto: string; valor: (serie: string) => number }> {
  const texto = await (await request.get(`${API}/metrics`)).text();
  const valor = (serie: string) => {
    const linha = texto.split('\n').find((l) => l.startsWith(`${serie} `));
    return linha ? Number(linha.split(' ').pop()) : 0;
  };
  return { texto, valor };
}

test('saas-multitenant: reserva, lista de espera, promoção e isolamento por tenant', async ({ page, request }) => {
  const hoje = new Intl.DateTimeFormat('pt-BR', { timeZone: FUSO, dateStyle: 'long' }).format(new Date());
  await narrar(
    page,
    'saas-multitenant: agenda de estúdios',
    `API Laravel multitenant com Keycloak, MySQL, Redis, Mailpit e Prometheus, tudo real via docker compose.
Vamos provar: login no Keycloak, aula recorrente com 2 vagas, lotação, lista de espera, promoção automática com e-mail,
isolamento entre estúdios (404), 401 sem token e as métricas.
${hoje}`,
    { pausaMs: 4000 },
  );

  const antes = await metricas(request);

  // 1. Keycloak: login pela tela do realm.
  await narrar(page, '1. Login no Keycloak', 'Abrimos o console de conta do realm "estudios" e entramos como a admin do Estúdio Lótus.');
  await page.goto(`${KEYCLOAK}/realms/estudios/account`);
  await page.locator('#username').waitFor();
  await page.waitForTimeout(2500);
  await page.locator('#username').fill(USUARIOS.admin);
  await page.locator('#password').fill(SENHA);
  await page.locator('#kc-login').click();
  await expect(page.getByText(USUARIOS.admin).or(page.getByText('Ana')).first()).toBeVisible({ timeout: 30_000 });
  await page.waitForTimeout(3000);

  // 2. Tokens reais (password grant de DEV) para todos os atores.
  const tokens = {} as Record<Papel, string>;
  const resumoTokens: string[] = [];
  for (const papel of Object.keys(USUARIOS) as Papel[]) {
    const resp = await request.post(`${KEYCLOAK}/realms/estudios/protocol/openid-connect/token`, {
      form: { grant_type: 'password', client_id: 'studio-web', username: USUARIOS[papel], password: SENHA },
    });
    expect(resp.status(), `token de ${USUARIOS[papel]}`).toBe(200);
    tokens[papel] = (await resp.json()).access_token;
    const claims = decodificar(tokens[papel]);
    const papeis = (claims.realm_access?.roles ?? []).filter((r: string) => !r.startsWith('default-') && !['offline_access', 'uma_authorization'].includes(r));
    resumoTokens.push(`${USUARIOS[papel].padEnd(34)} tenant=${String(claims.tenant).padEnd(20)} papéis=${papeis.join(',')}`);
  }
  const claimsAdmin = decodificar(tokens.admin);
  await narrar(page, '2. Tokens JWT reais emitidos pelo Keycloak', 'Um token RS256 por usuário. A API lê o estúdio só da claim "tenant", nunca de header ou parâmetro.', {
    saida: `${resumoTokens.join('\n')}\n\nclaims do token da admin:\n${JSON.stringify(
      { iss: claimsAdmin.iss, aud: claimsAdmin.aud, sub: claimsAdmin.sub, tenant: claimsAdmin.tenant, email: claimsAdmin.email },
      null,
      2,
    )}`,
    rotuloSaida: 'POST /realms/estudios/protocol/openid-connect/token (claims decodificadas)',
    pausaMs: 5000,
  });

  const get = (papel: Papel | null, caminho: string) =>
    request.get(`${API}${caminho}`, { headers: papel ? { Authorization: `Bearer ${tokens[papel]}` } : {} });
  const post = (papel: Papel | null, caminho: string, data?: unknown) =>
    request.post(`${API}${caminho}`, { headers: papel ? { Authorization: `Bearer ${tokens[papel]}` } : {}, data });

  // 3. O instrutor existe e tem um id (JIT): a admin vai usá-lo na aula.
  const me = await chamada('GET', '/api/v1/me', await get('instrutor', '/api/v1/me'));
  expect(me.status).toBe(200);
  expect(me.json.data.role).toBe('instructor');
  const instrutorId = me.json.data.id;
  await narrar(page, '3. Quem é o instrutor', 'GET /me com o token do instrutor: usuário, papel e estúdio vêm do token validado pela API.', { chamada: me });

  // 4. Admin cria aula recorrente com capacidade 2.
  const inicio = dataLocal(2);
  const segunda = dataLocal(4);
  const serieReq = {
    title: 'Hatha Yoga Evidência',
    instructor_id: instrutorId,
    weekdays: [...new Set([diaIso(inicio), diaIso(segunda)])],
    start_time: '07:00',
    duration_minutes: 60,
    capacity: 2,
    starts_on: inicio,
    ends_on: dataLocal(16),
  };
  const serie = await chamada('POST', '/api/v1/class-series', await post('admin', '/api/v1/class-series', serieReq));
  expect(serie.status).toBe(201);
  expect(serie.json.data.capacity).toBe(2);
  expect(serie.json.data.sessions_count).toBeGreaterThanOrEqual(4);
  await narrar(page, '4. Admin cria uma aula recorrente com 2 vagas', `Corpo enviado: ${JSON.stringify(serieReq)}`, { chamada: serie, pausaMs: 4000 });

  const grade = await chamada(
    'GET',
    `/api/v1/sessions?from=${inicio}&to=${inicio}`,
    await get('alunaA', `/api/v1/sessions?from=${inicio}&to=${inicio}`),
  );
  expect(grade.status).toBe(200);
  const aula = grade.json.data.find((s: any) => s.series_id === serie.json.data.id);
  expect(aula).toBeTruthy();
  expect(aula.available_spots).toBe(2);
  const sessionId: string = aula.id;
  await narrar(page, '4b. A aula aparece na grade do aluno', 'Horário no fuso do estúdio (07:00 em São Paulo) e em UTC.', {
    chamada: { ...grade, corpo: { data: [aula] } },
  });

  // 5. A e B reservam: aula lotada.
  const reservaA = await chamada('POST', `/api/v1/sessions/${sessionId}/bookings`, await post('alunaA', `/api/v1/sessions/${sessionId}/bookings`));
  expect(reservaA.status).toBe(201);
  expect(reservaA.json.data.status).toBe('confirmed');
  await narrar(page, '5. Aluna A (Alice) reserva', 'Há vaga: a reserva é confirmada.', { chamada: reservaA });

  const reservaB = await chamada('POST', `/api/v1/sessions/${sessionId}/bookings`, await post('alunaB', `/api/v1/sessions/${sessionId}/bookings`));
  expect(reservaB.status).toBe(201);
  expect(reservaB.json.data.status).toBe('confirmed');
  await narrar(page, '6. Aluna B (Beatriz) reserva', 'Segunda e última vaga confirmada.', { chamada: reservaB });

  const lotada = await chamada('GET', `/api/v1/sessions/${sessionId}`, await get('alunoC', `/api/v1/sessions/${sessionId}`));
  expect(lotada.json.data.available_spots).toBe(0);
  expect(lotada.json.data.confirmed_count).toBe(2);
  await narrar(page, '7. A aula está lotada', 'available_spots = 0, confirmed_count = 2.', { chamada: lotada });

  // 6. C entra na lista de espera.
  const reservaC = await chamada('POST', `/api/v1/sessions/${sessionId}/bookings`, await post('alunoC', `/api/v1/sessions/${sessionId}/bookings`));
  expect(reservaC.status).toBe(201);
  expect(reservaC.json.data.status).toBe('waitlisted');
  await narrar(page, '8. Aluno C (Caio) tenta reservar', 'Sem vaga: regra de negócio coloca o Caio na lista de espera.', { chamada: reservaC });

  const presenca1 = await chamada('GET', `/api/v1/sessions/${sessionId}/bookings`, await get('admin', `/api/v1/sessions/${sessionId}/bookings`));
  expect(presenca1.status).toBe(200);
  const resumoPresenca = (c: any) => c.json.data.map((b: any) => `${b.member.name.padEnd(26)} ${b.status}`).join('\n');
  await narrar(page, '9. Lista de presença vista pela admin', 'Duas confirmadas e um na fila.', {
    saida: resumoPresenca(presenca1),
    rotuloSaida: `GET /api/v1/sessions/${sessionId}/bookings -> ${presenca1.status}`,
  });

  // 7. A cancela, C é promovido.
  const cancel = await chamada('POST', `/api/v1/bookings/${reservaA.json.data.id}/cancel`, await post('alunaA', `/api/v1/bookings/${reservaA.json.data.id}/cancel`));
  expect(cancel.status).toBe(200);
  expect(cancel.json.data.canceled.status).toBe('canceled');
  expect(cancel.json.data.promoted?.id).toBe(reservaC.json.data.id);
  expect(cancel.json.data.promoted?.status).toBe('confirmed');
  await narrar(page, '10. Aluna A cancela: Caio é promovido', 'Na mesma transação do cancelamento, o primeiro da fila vira confirmado ("promoted").', {
    chamada: cancel,
    pausaMs: 4000,
  });

  const presenca2 = await chamada('GET', `/api/v1/sessions/${sessionId}/bookings`, await get('admin', `/api/v1/sessions/${sessionId}/bookings`));
  expect(presenca2.json.data.map((b: any) => b.status)).toEqual(['confirmed', 'confirmed']);
  await narrar(page, '11. Lista de presença depois do cancelamento', 'Beatriz e Caio confirmados; fila vazia.', {
    saida: resumoPresenca(presenca2),
    rotuloSaida: `GET /api/v1/sessions/${sessionId}/bookings -> ${presenca2.status}`,
  });

  // 8. E-mail de promoção no Mailpit (enviado pelo worker via fila Redis).
  let mensagemId = '';
  await expect
    .poll(
      async () => {
        const r = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:${USUARIOS.alunoC}`)}`);
        const msgs = (await r.json()).messages ?? [];
        const recente = msgs.find((m: any) => new Date(m.Created).getTime() >= Date.now() - 5 * 60_000);
        mensagemId = recente?.ID ?? '';
        return mensagemId;
      },
      { timeout: 30_000 },
    )
    .not.toBe('');
  await narrar(page, '12. O e-mail de promoção chegou', 'O evento WaitlistPromoted foi para a fila Redis; o worker enviou o e-mail ao Caio. Vamos ver no Mailpit.');
  await page.goto(MAILPIT);
  await page.waitForTimeout(2500);
  await page.goto(`${MAILPIT}/view/${mensagemId}`);
  const assunto = page.getByText('Sua vaga em Hatha Yoga Evidência foi confirmada').first();
  await expect(assunto).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText(USUARIOS.alunoC).first()).toBeVisible();
  await assunto.highlight();
  await page.waitForTimeout(4000);

  // 9. Isolamento por tenant.
  const outroGet = await chamada('GET', `/api/v1/sessions/${sessionId}`, await get('adminOutro', `/api/v1/sessions/${sessionId}`));
  expect(outroGet.status).toBe(404);
  await narrar(page, '13. Admin de outro estúdio tenta ver a aula', 'Token do Pilates Equilíbrio, mesmo id de aula: 404. O recurso simplesmente não existe para outro tenant.', {
    chamada: outroGet,
  });
  const outroPost = await chamada(
    'POST',
    `/api/v1/sessions/${sessionId}/bookings`,
    await post('alunoOutro', `/api/v1/sessions/${sessionId}/bookings`),
  );
  expect(outroPost.status).toBe(404);
  await narrar(page, '14. Aluno de outro estúdio tenta reservar a aula', 'Também 404: não dá para reservar aula de outro estúdio.', { chamada: outroPost });

  // 10. Sem token.
  const semToken = await chamada('POST', `/api/v1/sessions/${sessionId}/bookings`, await post(null, `/api/v1/sessions/${sessionId}/bookings`));
  expect(semToken.status).toBe(401);
  await narrar(page, '15. Reserva sem token', 'Sem Authorization: 401 em Problem Details.', { chamada: semToken });
  const forjado = await chamada(
    'POST',
    `/api/v1/sessions/${sessionId}/bookings`,
    await request.post(`${API}/api/v1/sessions/${sessionId}/bookings`, { headers: { Authorization: 'Bearer eyJhbGciOiJub25lIn0.eyJ0ZW5hbnQiOiJlc3R1ZGlvLWxvdHVzIn0.' } }),
  );
  expect(forjado.status).toBe(401);
  await narrar(page, '16. Token forjado (alg "none")', 'Token sem assinatura com tenant inventado: 401.', { chamada: forjado });

  // 11. Métricas.
  const depois = await metricas(request);
  const confirmadas = depois.valor('saas_bookings_total{result="confirmed"}') - antes.valor('saas_bookings_total{result="confirmed"}');
  const espera = depois.valor('saas_bookings_total{result="waitlisted"}') - antes.valor('saas_bookings_total{result="waitlisted"}');
  const promocoes = depois.valor('saas_waitlist_promotions_total') - antes.valor('saas_waitlist_promotions_total');
  expect(confirmadas).toBe(2);
  expect(espera).toBe(1);
  expect(promocoes).toBe(1);
  await narrar(page, '17. /metrics: reservas e promoções', `Nesta execução: +${confirmadas} confirmadas, +${espera} em espera, +${promocoes} promoção.`, {
    saida: depois.texto
      .split('\n')
      .filter((l) => /saas_(bookings_total|waitlist_promotions_total)/.test(l))
      .join('\n'),
    rotuloSaida: 'GET /metrics (linhas de reservas e promoções)',
    pausaMs: 4000,
  });

  await expect
    .poll(
      async () => {
        const r = await request.get(`${PROMETHEUS}/api/v1/query?query=saas_waitlist_promotions_total`);
        return Number((await r.json()).data.result[0]?.value[1] ?? 0);
      },
      { timeout: 45_000, intervals: [3000] },
    )
    .toBe(depois.valor('saas_waitlist_promotions_total'));
  await page.goto(`${PROMETHEUS}/query?g0.expr=${encodeURIComponent('saas_bookings_total or saas_waitlist_promotions_total')}&g0.tab=table`);
  await expect(page.getByText('saas_waitlist_promotions_total').first()).toBeVisible({ timeout: 15_000 });
  await page.waitForTimeout(4000);

  await narrar(
    page,
    'Resumo: o que foi provado',
    `- Tokens reais do Keycloak (realm estudios) validados pela API via JWKS.
- Admin criou aula recorrente com 2 vagas; A e B lotaram a aula.
- C entrou na lista de espera; o cancelamento de A promoveu C na hora.
- O worker enviou o e-mail de promoção (Mailpit).
- Outro estúdio recebe 404 ao ler ou reservar a aula; sem token ou com token forjado, 401.
- /metrics e Prometheus registraram as reservas e a promoção.`,
    { pausaMs: 5000 },
  );
});
