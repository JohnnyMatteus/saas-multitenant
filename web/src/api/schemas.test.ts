import { describe, expect, it } from 'vitest'
import { bookingSchema, wasPromoted } from './schemas'

const base = { id: 'b1', session_id: 's1', status: 'confirmed', waitlist_position: null, canceled_at: null }

describe('mapeamento da API', () => {
  it('reconhece quem saiu da fila pelo intervalo entre entrada e confirmação', () => {
    const direct = bookingSchema.parse({ ...base, queued_at: '2026-03-09T10:00:00Z', confirmed_at: '2026-03-09T10:00:00Z' })
    const promoted = bookingSchema.parse({ ...base, queued_at: '2026-03-09T10:00:00Z', confirmed_at: '2026-03-09T12:00:00Z' })
    expect(wasPromoted(direct)).toBe(false)
    expect(wasPromoted(promoted)).toBe(true)
  })
  it('rejeita status desconhecido na fronteira', () => {
    expect(() => bookingSchema.parse({ ...base, status: 'x', queued_at: 'a', confirmed_at: null })).toThrow()
  })
})
