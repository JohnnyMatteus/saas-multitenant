import { describe, expect, it } from 'vitest'
import { addLocalDays, deadlinePhrase, isoWeekday, localDateOf, previewOccurrences, startOfLocalWeek, studioToday, windowLabel } from './time'

const SP = 'America/Sao_Paulo'

describe('datas no fuso do estúdio', () => {
  it('"hoje" segue o estúdio, não o UTC', () => {
    expect(studioToday(SP, new Date('2026-03-10T01:30:00Z'))).toBe('2026-03-09')
    expect(localDateOf('2026-03-10T01:30:00Z', SP)).toBe('2026-03-09')
  })
  it('aritmética de calendário', () => {
    expect(addLocalDays('2026-02-27', 2)).toBe('2026-03-01')
    expect(isoWeekday('2026-03-09')).toBe(1)
    expect(startOfLocalWeek('2026-03-15')).toBe('2026-03-09')
  })
  it('frases de prazo e janela', () => {
    const now = new Date('2026-03-09T12:00:00Z')
    expect(deadlinePhrase('2026-03-09T21:00:00Z', SP, now)).toBe('18h de hoje')
    expect(windowLabel(120)).toBe('2 horas antes')
    expect(windowLabel(0)).toBe('até o início da aula')
  })
  it('prévia da recorrência respeita dias e fuso', () => {
    const out = previewOccurrences({ weekdays: [1, 3], startsOn: '2026-03-09', endsOn: '2026-03-31', startTime: '07:00', durationMinutes: 50, timezone: SP, limit: 3 })
    expect(out.map((o) => o.localDate)).toEqual(['2026-03-09', '2026-03-11', '2026-03-16'])
    expect(out[0]?.startsAt.toISOString()).toBe('2026-03-09T10:00:00.000Z')
  })
})
