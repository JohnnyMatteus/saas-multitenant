import type { Page } from '@playwright/test';

export interface Chamada {
  metodo: string;
  url: string;
  status: number;
  corpo: unknown;
}

export interface Detalhes {
  chamada?: Chamada;
  saida?: string;
  rotuloSaida?: string;
  pausaMs?: number;
}

const escapar = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const formatar = (corpo: unknown) => (typeof corpo === 'string' ? corpo : JSON.stringify(corpo, null, 2));

export async function narrar(page: Page, titulo: string, detalhe: string, extra: Detalhes = {}) {
  const { chamada, saida, rotuloSaida, pausaMs = 2800 } = extra;
  const cor = !chamada ? '#64748b' : chamada.status < 300 ? '#16a34a' : chamada.status < 500 ? '#d97706' : '#dc2626';
  const blocoChamada = chamada
    ? `<div class="req"><span class="metodo">${escapar(chamada.metodo)}</span>
         <span class="url">${escapar(chamada.url)}</span>
         <span class="status" style="background:${cor}">${chamada.status}</span></div>
       <pre>${escapar(formatar(chamada.corpo))}</pre>`
    : '';
  const blocoSaida = saida
    ? `<div class="rotulo">${escapar(rotuloSaida ?? 'saída real')}</div><pre>${escapar(saida)}</pre>`
    : '';
  await page.setContent(`<!doctype html><html><head><meta charset="utf-8"><style>
    body { margin:0; height:100vh; display:flex; align-items:center; justify-content:center;
           background:#0f172a; font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; color:#e2e8f0; }
    .card { width:1160px; max-height:660px; overflow:hidden; background:#1e293b; border-radius:18px;
            padding:28px 40px; box-shadow:0 20px 60px rgba(0,0,0,.5); border-top:6px solid #a78bfa; }
    h1 { margin:0 0 10px; font-size:32px; color:#f8fafc; }
    p { margin:0 0 14px; font-size:20px; line-height:1.4; color:#cbd5e1; white-space:pre-line; }
    .req { display:flex; gap:12px; align-items:center; margin-bottom:8px; font-family: Menlo, monospace; font-size:17px; }
    .metodo { background:#334155; padding:4px 10px; border-radius:6px; font-weight:700; color:#c4b5fd; }
    .url { color:#e2e8f0; word-break:break-all; }
    .status { padding:4px 12px; border-radius:6px; font-weight:700; color:white; }
    .rotulo { font-size:14px; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; margin:6px 0; }
    pre { margin:0 0 10px; background:#0b1220; padding:14px 18px; border-radius:10px; font-size:14px;
          line-height:1.4; color:#a7f3d0; white-space:pre-wrap; max-height:400px; overflow:hidden; }
  </style></head><body><div class="card">
    <h1>${escapar(titulo)}</h1><p>${escapar(detalhe)}</p>${blocoChamada}${blocoSaida}
  </div></body></html>`);
  await page.waitForTimeout(pausaMs);
}
