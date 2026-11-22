import { describe, expect, it } from 'vitest'
import { filenameFrom } from './download'

describe('filenameFrom', () => {
  it('lê o nome enviado pela API', () => {
    expect(filenameFrom('attachment; filename="estudio-lotus-20261122-120000.json"', 'x.json')).toBe('estudio-lotus-20261122-120000.json')
  })

  it('não aceita caminho no nome e cai no padrão', () => {
    expect(filenameFrom('attachment; filename="../../etc/passwd"', 'x.json')).toBe('x.json')
    expect(filenameFrom(null, 'x.json')).toBe('x.json')
  })
})
