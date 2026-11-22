import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'
import type { Me, Role } from '@/api/schemas'

export function homeFor(role: Role): RouteLocationRaw {
  return role === 'student' ? { name: 'agenda' } : { name: 'today' }
}

export interface GuardDeps {
  currentUser: () => Promise<unknown>
  login: (returnTo: string) => Promise<unknown>
  loadMe: () => Promise<Me>
}

/**
 * Decide a navegação: sem sessão, rota protegida vai ao login OIDC; com sessão, o papel
 * vem de /me (a API, que valida o token), nunca de algo guardado no cliente.
 */
export async function navigationGuard(
  to: Pick<RouteLocationNormalized, 'name' | 'matched' | 'fullPath'>,
  deps: GuardDeps,
): Promise<true | false | RouteLocationRaw> {
  const roles = to.matched.flatMap((r) => r.meta.roles ?? [])
  if (to.name === 'callback') return true

  if (!(await deps.currentUser())) {
    if (roles.length) {
      await deps.login(to.fullPath)
      return false
    }
    return true
  }

  const me = await deps.loadMe()
  if (to.name === 'login') return homeFor(me.role)

  // Cada rota pode restringir mais que a mãe (ex.: configurações só para admin).
  const allowed = to.matched.every((r) => !r.meta.roles || r.meta.roles.includes(me.role))
  return allowed ? true : homeFor(me.role)
}
