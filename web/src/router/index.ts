import { createRouter, createWebHistory, type RouteLocationRaw } from 'vue-router'
import { api } from '@/api/endpoints'
import { keys } from '@/api/queries'
import type { Role } from '@/api/schemas'
import { currentUser, login } from '@/auth/oidc'
import { queryClient } from '@/plugins/query'

declare module 'vue-router' {
  interface RouteMeta {
    roles?: Role[]
    title?: string
  }
}

export function homeFor(role: Role): RouteLocationRaw {
  return role === 'student' ? { name: 'agenda' } : { name: 'today' }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'login', component: () => import('@/features/auth/LoginPage.vue') },
    { path: '/callback', name: 'callback', component: () => import('@/features/auth/CallbackPage.vue') },
    {
      path: '/estudio',
      component: () => import('@/features/studio/StudioShell.vue'),
      meta: { roles: ['studio_admin', 'instructor'] },
      children: [
        { path: '', redirect: { name: 'today' } },
        { path: 'hoje', name: 'today', component: () => import('@/features/studio/TodayPage.vue'), meta: { title: 'Hoje' } },
        { path: 'semana', name: 'week', component: () => import('@/features/studio/WeekPage.vue'), meta: { title: 'Semana' } },
        { path: 'aulas/:id', name: 'roster', component: () => import('@/features/studio/RosterPage.vue'), meta: { title: 'Lista da aula' } },
        {
          path: 'configuracoes',
          name: 'settings',
          component: () => import('@/features/studio/SettingsPage.vue'),
          meta: { title: 'Configurações', roles: ['studio_admin'] },
        },
      ],
    },
    {
      path: '/app',
      component: () => import('@/features/student/StudentShell.vue'),
      meta: { roles: ['student'] },
      children: [
        { path: '', redirect: { name: 'agenda' } },
        { path: 'agenda', name: 'agenda', component: () => import('@/features/student/AgendaPage.vue'), meta: { title: 'Agenda' } },
        { path: 'minhas-aulas', name: 'my-classes', component: () => import('@/features/student/MyBookingsPage.vue'), meta: { title: 'Minhas aulas' } },
        { path: 'para-voce', name: 'for-you', component: () => import('@/features/student/ForYouPage.vue'), meta: { title: 'Para você' } },
        { path: 'perfil', name: 'profile', component: () => import('@/features/student/ProfilePage.vue'), meta: { title: 'Perfil' } },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const roles = to.matched.flatMap((r) => r.meta.roles ?? [])
  if (to.name === 'callback') return true

  const user = await currentUser()
  if (!user) {
    if (roles.length) {
      await login(to.fullPath)
      return false
    }
    return true
  }

  const me = await queryClient.ensureQueryData({ queryKey: keys.me, queryFn: api.me })
  if (to.name === 'login') return homeFor(me.role)

  // Cada rota pode restringir mais que a mãe (ex.: configurações só para admin).
  const allowed = to.matched.every((r) => !r.meta.roles || r.meta.roles.includes(me.role))
  return allowed ? true : homeFor(me.role)
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Respiro` : 'Respiro · agenda do estúdio'
})

export default router
