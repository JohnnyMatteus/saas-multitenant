import { describe, expect, it } from 'vitest'
import { formatBRL, parseReais, parseReaisToCents } from './money'

describe('dinheiro em centavos', () => {
  it('formata centavos em reais', () => {
    expect(formatBRL(32000).replace(/\s/g, ' ')).toBe('R$ 320,00')
    expect(formatBRL(5).replace(/\s/g, ' ')).toBe('R$ 0,05')
  })

  it('lê o formato brasileiro sem erro de ponto flutuante', () => {
    expect(parseReaisToCents('320')).toBe(32000)
    expect(parseReaisToCents('320,5')).toBe(32050)
    expect(parseReaisToCents('320,50')).toBe(32050)
    expect(parseReaisToCents('19,99')).toBe(1999)
    expect(parseReaisToCents('0,05')).toBe(5)
    expect(parseReaisToCents(' 1.320 ')).toBe(132000)
    expect(parseReaisToCents('1.320,50')).toBe(132050)
    expect(parseReaisToCents('1.320,5')).toBe(132050)
    expect(parseReaisToCents('1.234.567,89')).toBe(123456789)
  })

  it('ponto que não é milhar nunca vira milhar: 320.50 e 1.5 são recusados, não adivinhados', () => {
    for (const text of ['320.50', '1.5', '1.320.5', '12.34,5', '1.32', '1320.00', '.50', '1.', '1.3200']) {
      expect(parseReaisToCents(text), text).toBeNull()
    }
    expect(parseReais('320.50')).toMatchObject({ error: expect.stringContaining('ponto não separa centavos') })
    expect(parseReais('1.5')).toMatchObject({ error: expect.stringContaining('vírgula') })
  })

  it('recusa o que não é um valor positivo', () => {
    for (const text of ['', ' ', 'abc', '0', '0,00', '-5', '10,999', '1,2,3', 'R$ 10', '1e3']) {
      expect(parseReaisToCents(text), text).toBeNull()
    }
  })
})
