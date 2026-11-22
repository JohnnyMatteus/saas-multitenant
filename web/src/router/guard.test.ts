import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({ currentUser: vi.fn(), login: vi.fn(), me: vi.fn() }))
vi.mock('@/auth/oidc', () => ({ currentUser: mocks.currentUser, login: mocks.login }))
vi.mock('@/api/endpoints', () => ({ api: { me: mocks.me } }))

const meOf = (role: string) => ({ id: '1', name: 'Pessoa', email: null, role, studio: { id: 's', slug: 'x', name: 'X', timezone: 'America/Sao_Paulo' } })

async function freshRouter() {
  vi.resetModules()
  return (await import('./index')).default
}

describe('guarda do router', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mocks.login.mockResolvedValue(undefined)
  })

  it('sem sessão, rota protegida manda para o login OIDC e não navega', async () => {
    mocks.currentUser.mockResolvedValue(null)
    const router = await freshRouter()

    await router.push('/estudio/semana')

    expect(mocks.login).toHaveBeenCalledWith('/estudio/semana')
    expect(router.currentRoute.value.path).not.toBe('/estudio/semana')
  })

  it('aluno não entra no painel do estúdio', async () => {
    mocks.currentUser.mockResolvedValue({ access_token: 't' })
    mocks.me.mockResolvedValue(meOf('student'))
    const router = await freshRouter()

    await router.push('/estudio/hoje')

    expect(router.currentRoute.value.name).toBe('agenda')
  })

  it('instrutor não abre as configurações do estúdio, só o admin', async () => {
    mocks.currentUser.mockResolvedValue({ access_token: 't' })
    mocks.me.mockResolvedValue(meOf('instructor'))
    const router = await freshRouter()

    await router.push('/estudio/configuracoes')
    expect(router.currentRoute.value.name).toBe('today')

    mocks.me.mockResolvedValue(meOf('studio_admin'))
    const admin = await freshRouter()
    await admin.push('/estudio/configuracoes')
    expect(admin.currentRoute.value.name).toBe('settings')
  })

  it('o papel vem da API, não do cliente: aluno autenticado vai para a agenda ao abrir a raiz', async () => {
    mocks.currentUser.mockResolvedValue({ access_token: 't' })
    mocks.me.mockResolvedValue(meOf('student'))
    const router = await freshRouter()

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('agenda')
  })
})
