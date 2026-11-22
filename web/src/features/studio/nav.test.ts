import { describe, expect, it } from 'vitest'
import { studioNav } from './nav'

// Espelha as rotas de /estudio com papel restrito em router/index.ts: a tela só existe de verdade se houver link para ela.
const adminOnlyRoutes = ['packages-admin', 'settings']

describe('menu do painel do estúdio', () => {
  it('o admin vê Pacotes e Ajustes, além de Hoje e Semana', () => {
    const names = studioNav('studio_admin').map((i) => i.to.name)

    expect(names).toEqual(['today', 'week', 'packages-admin', 'settings'])
    for (const route of adminOnlyRoutes) expect(names).toContain(route)
  })

  it('o instrutor não vê Pacotes nem Ajustes', () => {
    const names = studioNav('instructor').map((i) => i.to.name)

    expect(names).toEqual(['today', 'week'])
    for (const route of adminOnlyRoutes) expect(names).not.toContain(route)
  })

  it('sem papel carregado ainda, só o básico', () => {
    expect(studioNav(undefined)).toHaveLength(2)
  })
})
