import { describe, expect, it } from 'vitest'
import { formatBRL, parseReaisToCents } from './money'

describe('dinheiro em centavos', () => {
  it('formata centavos em reais', () => {
    expect(formatBRL(32000).replace(/\s/g, ' ')).toBe('R$ 320,00')
    expect(formatBRL(5).replace(/\s/g, ' ')).toBe('R$ 0,05')
  })

  it('lê reais digitados sem erro de ponto flutuante', () => {
    expect(parseReaisToCents('320')).toBe(32000)
    expect(parseReaisToCents('320,50')).toBe(32050)
    expect(parseReaisToCents('1.320,5')).toBe(132050)
    expect(parseReaisToCents('19,99')).toBe(1999)
  })

  it('recusa o que não é um valor positivo', () => {
    for (const text of ['', 'abc', '0', '-5', '10,999', '1,2,3']) expect(parseReaisToCents(text)).toBeNull()
  })
})
