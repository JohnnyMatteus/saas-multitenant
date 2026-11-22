import { mdiCalendarTodayOutline, mdiCalendarWeekOutline, mdiCogOutline, mdiTicketOutline } from '@mdi/js'
import type { Role } from '@/api/schemas'

export interface NavItem {
  to: { name: string }
  icon: string
  label: string
}

/** Itens do menu do painel do estúdio por papel: cada rota de `/estudio` com papel restrito precisa de um link aqui. */
export function studioNav(role: Role | undefined): NavItem[] {
  const common: NavItem[] = [
    { to: { name: 'today' }, icon: mdiCalendarTodayOutline, label: 'Hoje' },
    { to: { name: 'week' }, icon: mdiCalendarWeekOutline, label: 'Semana' },
  ]
  if (role !== 'studio_admin') return common
  return [
    ...common,
    { to: { name: 'packages-admin' }, icon: mdiTicketOutline, label: 'Pacotes' },
    { to: { name: 'settings' }, icon: mdiCogOutline, label: 'Ajustes' },
  ]
}

