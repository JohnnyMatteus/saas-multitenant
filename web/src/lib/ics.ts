import type { ClassSession } from '@/api/schemas'

const stamp = (iso: string) => iso.replace(/[-:]/g, '').replace(/\.\d+/, '')

// Arquivo .ics gerado no navegador com os dados da aula; nada vai a terceiros.
export function downloadIcs(session: ClassSession, studioName: string) {
  const body = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Respiro//Agenda do estudio//PT',
    'BEGIN:VEVENT',
    `UID:${session.id}@respiro`,
    `DTSTAMP:${stamp(new Date().toISOString())}`,
    `DTSTART:${stamp(session.starts_at)}`,
    `DTEND:${stamp(session.ends_at)}`,
    `SUMMARY:${session.title} · ${studioName}`,
    `DESCRIPTION:Com ${session.instructor.name}`,
    'END:VEVENT',
    'END:VCALENDAR',
  ].join('\r\n')
  const url = URL.createObjectURL(new Blob([body], { type: 'text/calendar' }))
  const a = document.createElement('a')
  a.href = url
  a.download = `${session.title.toLowerCase().replace(/\s+/g, '-')}.ics`
  a.click()
  URL.revokeObjectURL(url)
}
