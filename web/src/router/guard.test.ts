import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Me, Role } from '@/api/schemas'
import { navigationGuard, type GuardDeps } from './guard'

const meOf = (role: Role): Me => ({
  id: '1',
  name: 'Pessoa',
  email: null,
  role,
  studio: { id: 's', slug: 'x', name: 'X', timezone: 'America/Sao_Paulo' },
})

// Mesma estrutura das rotas reais: filha herda o papel da mãe e pode restringir mais.
const studio = { meta: { roles: ['studio_admin', 'instructor'] } }
const target = (name: string, ...matched: object[]) =>
  ({ name, fullPath: `/${name}`, matched: matched.length ? matched : [{ meta: {} }] }) as unknown as Parameters<typeof navigationGuard>[0]

describe('guarda do router', () => {
  const deps = { currentUser: vi.fn(), login: vi.fn(), loadMe: vi.fn() } satisfies GuardDeps
  beforeEach(() => {
    vi.resetAllMocks()
    deps.login.mockResolvedValue(undefined)
  })

  it('sem sessão, rota protegida manda para o login OIDC com o destino e não navega', async () => {
    deps.currentUser.mockResolvedValue(null)

    const result = await navigationGuard(target('week', studio), deps)

    expect(result).toBe(false)
    expect(deps.login).toHaveBeenCalledWith('/week')
  })

  it('sem sessão, a página de login e o callback seguem abertos', async () => {
    deps.currentUser.mockResolvedValue(null)

    expect(await navigationGuard(target('login'), deps)).toBe(true)
    expect(await navigationGuard(target('callback', studio), deps)).toBe(true)
    expect(deps.login).not.toHaveBeenCalled()
  })

  it('aluno não entra no painel do estúdio e volta para a agenda', async () => {
    deps.currentUser.mockResolvedValue({})
    deps.loadMe.mockResolvedValue(meOf('student'))

    expect(await navigationGuard(target('today', studio), deps)).toEqual({ name: 'agenda' })
  })

  it('instrutor não abre as configurações, só o admin', async () => {
    deps.currentUser.mockResolvedValue({})
    const settings = { meta: { roles: ['studio_admin'] } }

    deps.loadMe.mockResolvedValue(meOf('instructor'))
    expect(await navigationGuard(target('settings', studio, settings), deps)).toEqual({ name: 'today' })

    deps.loadMe.mockResolvedValue(meOf('studio_admin'))
    expect(await navigationGuard(target('settings', studio, settings), deps)).toBe(true)
  })

  it('autenticado que abre a raiz vai para a home do papel que a API devolveu', async () => {
    deps.currentUser.mockResolvedValue({})
    deps.loadMe.mockResolvedValue(meOf('student'))

    expect(await navigationGuard(target('login'), deps)).toEqual({ name: 'agenda' })
  })
})
