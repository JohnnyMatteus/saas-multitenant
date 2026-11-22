import { createRouter, createWebHistory } from 'vue-router'
import { api } from '@/api/endpoints'
import { keys } from '@/api/queries'
import type { Role } from '@/api/schemas'
import { currentUser, login } from '@/auth/oidc'
import { queryClient } from '@/plugins/query'
import { navigationGuard } from './guard'

declare module 'vue-router' {
  interface RouteMeta {
    roles?: Role[]
    title?: string
  }
}

const loadMe = () => queryClient.ensureQueryData({ queryKey: keys.me, queryFn: api.me })

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
          path: 'pacotes',
          name: 'packages-admin',
          component: () => import('@/features/studio/PackagesAdminPage.vue'),
          meta: { title: 'Pacotes', roles: ['studio_admin'] },
        },
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
        { path: 'pacotes', name: 'packages', component: () => import('@/features/student/PackagesPage.vue'), meta: { title: 'Pacotes' } },
        { path: 'perfil', name: 'profile', component: () => import('@/features/student/ProfilePage.vue'), meta: { title: 'Perfil' } },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach((to) => navigationGuard(to, { currentUser, login, loadMe }))

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Respiro` : 'Respiro · agenda do estúdio'
})

export default router
