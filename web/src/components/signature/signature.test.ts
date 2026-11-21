import { describe, expect, it } from 'vitest'
import { matRowCaption, matRowLabel } from './matRowLabel'
import { clampPercent, rulerPercent } from './ruler'

describe('fileira de tapetes', () => {
  it('descreve ocupação e fila para leitor de tela', () => {
    expect(matRowLabel(8, 8, 2)).toBe('8 de 8 tapetes ocupados, 2 na lista de espera')
    expect(matRowLabel(1, 0, 0, true)).toBe('0 de 1 tapete ocupado, aula em andamento')
  })
  it('não conta confirmados acima da capacidade', () => {
    expect(matRowLabel(6, 9, 0)).toContain('6 de 6')
  })
  it('legenda curta por estado', () => {
    expect(matRowCaption(6, 5, 0)).toBe('1 tapete livre')
    expect(matRowCaption(6, 6, 3)).toBe('lotada · 3 na fila')
    expect(matRowCaption(6, 2, 0, true)).toBe('em andamento')
  })
})

describe('régua do dia', () => {
  it('posiciona minutos entre 6h e 22h', () => {
    expect(rulerPercent(6 * 60)).toBe(0)
    expect(rulerPercent(14 * 60)).toBe(50)
    expect(rulerPercent(5 * 60)).toBeNull()
    expect(clampPercent(23 * 60)).toBe(100)
  })
})
