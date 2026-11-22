// Gera os screenshots do README contra a stack local (make up). Uso: node screenshots.mjs
import { chromium } from '@playwright/test'

const BASE = process.env.WEB_URL ?? 'http://localhost:5173'
const OUT = new URL('../../docs/screenshots/', import.meta.url).pathname
const PASSWORD = 'dev-only-change-me'

async function login(browser, email, viewport, colorScheme = 'light') {
  const context = await browser.newContext({ viewport, colorScheme, locale: 'pt-BR', deviceScaleFactor: 1 })
  const page = await context.newPage()
  await page.goto(BASE)
  await page.getByRole('button', { name: /entrar/i }).first().click()
  await page.fill('#username', email)
  await page.fill('#password', PASSWORD)
  await page.click('#kc-login')
  await page.waitForURL(/\/(estudio|app)/)
  return page
}

const shot = async (page, name) => {
  await page.waitForLoadState('networkidle')
  await page.waitForTimeout(800)
  await page.screenshot({ path: `${OUT}${name}.png` })
}

const browser = await chromium.launch()
const desktop = { width: 1280, height: 800 }

let page = await login(browser, 'admin.lotus@example.com', desktop)
await shot(page, 'painel-hoje')
await page.goto(`${BASE}/estudio/semana`)
await shot(page, 'painel-semana')
await page.locator('a[href^="/estudio/aulas/"]').first().click()
await shot(page, 'painel-lista-de-presenca')
await page.close()

page = await login(browser, 'aluna.lotus@example.com', { width: 390, height: 844 })
await shot(page, 'app-agenda')
await page.goto(`${BASE}/app/minhas-aulas`)
await shot(page, 'app-minhas-aulas')
await page.goto(`${BASE}/app/para-voce`)
await shot(page, 'app-para-voce')
await page.close()

page = await login(browser, 'admin.lotus@example.com', desktop, 'dark')
await shot(page, 'painel-hoje-escuro')
await browser.close()
