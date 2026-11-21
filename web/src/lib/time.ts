import { addDays, format, getISODay, parseISO } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { formatInTimeZone, fromZonedTime } from 'date-fns-tz'

// Datas "locais do estúdio" circulam como 'yyyy-MM-dd'. Para aritmética de
// calendário usamos meio-dia UTC, que nunca cai em virada de horário de verão.
const atNoon = (localDate: string) => parseISO(`${localDate}T12:00:00Z`)

export function studioToday(timezone: string, now: Date = new Date()): string {
  return formatInTimeZone(now, timezone, 'yyyy-MM-dd')
}

export function addLocalDays(localDate: string, days: number): string {
  return addDays(atNoon(localDate), days).toISOString().slice(0, 10)
}

export function isoWeekday(localDate: string): number {
  return getISODay(atNoon(localDate))
}

export function startOfLocalWeek(localDate: string): string {
  return addLocalDays(localDate, 1 - isoWeekday(localDate))
}

export function formatLocalDate(localDate: string, pattern: string): string {
  return format(atNoon(localDate), pattern, { locale: ptBR })
}

export function formatInStudio(iso: string | Date, timezone: string, pattern: string): string {
  return formatInTimeZone(typeof iso === 'string' ? parseISO(iso) : iso, timezone, pattern, { locale: ptBR })
}

export const hhmm = (iso: string | Date, timezone: string) => formatInStudio(iso, timezone, 'HH:mm')

export function localDateOf(iso: string, timezone: string): string {
  return formatInStudio(iso, timezone, 'yyyy-MM-dd')
}

// Minutos desde a meia-noite no fuso do estúdio (posiciona aulas na régua e na grade).
export function minutesOfDay(iso: string | Date, timezone: string): number {
  const [h, m] = formatInStudio(iso, timezone, 'HH:mm').split(':').map(Number)
  return (h ?? 0) * 60 + (m ?? 0)
}

export function zonedToUtc(localDate: string, time: string, timezone: string): Date {
  return fromZonedTime(`${localDate}T${time}:00`, timezone)
}

const CITY_NAMES: Record<string, string> = {
  Sao_Paulo: 'São Paulo',
  Manaus: 'Manaus',
  Belem: 'Belém',
  Cuiaba: 'Cuiabá',
  Maceio: 'Maceió',
  Araguaina: 'Araguaína',
}

export function timezoneLabel(timezone: string): string {
  const city = timezone.split('/').pop() ?? timezone
  return `horário de ${CITY_NAMES[city] ?? city.replace(/_/g, ' ')}`
}

export function relativeDayLabel(localDate: string, today: string): string {
  if (localDate === today) return 'hoje'
  if (localDate === addLocalDays(today, 1)) return 'amanhã'
  return formatLocalDate(localDate, "EEEE, d 'de' MMMM")
}

// "cancele sem custo até 18h de hoje"
export function deadlinePhrase(deadlineIso: string, timezone: string, now: Date = new Date()): string {
  const day = relativeDayLabel(localDateOf(deadlineIso, timezone), studioToday(timezone, now))
  const time = formatInStudio(deadlineIso, timezone, "H'h'mm").replace(/h00$/, 'h')
  return day === 'hoje' || day === 'amanhã' ? `${time} de ${day}` : `${time} de ${day}`
}

export function windowLabel(minutes: number): string {
  if (minutes === 0) return 'até o início da aula'
  if (minutes % 1440 === 0) return minutes === 1440 ? '24 horas antes' : `${minutes / 1440} dias antes`
  if (minutes % 60 === 0) return minutes === 60 ? '1 hora antes' : `${minutes / 60} horas antes`
  return `${minutes} minutos antes`
}

export function ordinal(n: number): string {
  return `${n}º`
}

// Próximas ocorrências de uma recorrência semanal, como o backend gera.
export function previewOccurrences(input: {
  weekdays: number[]
  startsOn: string
  endsOn: string
  startTime: string
  durationMinutes: number
  timezone: string
  limit?: number
}): { localDate: string; startsAt: Date; endsAt: Date }[] {
  const out: { localDate: string; startsAt: Date; endsAt: Date }[] = []
  if (!input.weekdays.length || !input.startsOn || !input.endsOn || input.endsOn < input.startsOn) return out
  const limit = input.limit ?? 4
  for (let d = input.startsOn; d <= input.endsOn && out.length < limit; d = addLocalDays(d, 1)) {
    if (input.weekdays.includes(isoWeekday(d))) {
      const startsAt = zonedToUtc(d, input.startTime, input.timezone)
      out.push({ localDate: d, startsAt, endsAt: new Date(startsAt.getTime() + input.durationMinutes * 60_000) })
    }
  }
  return out
}
