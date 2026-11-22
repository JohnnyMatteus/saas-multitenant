const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/** Valores trafegam em centavos inteiros; só a tela formata. */
export function formatBRL(cents: number): string {
  return brl.format(cents / 100)
}

/** "320", "320,50" ou "1.320,5" -> centavos. Retorna null se não for um valor positivo válido. */
export function parseReaisToCents(text: string): number | null {
  const normalized = text.trim().replace(/\./g, '').replace(',', '.')
  if (!/^\d+(\.\d{1,2})?$/.test(normalized)) return null
  const cents = Math.round(Number(normalized) * 100)
  return cents > 0 ? cents : null
}
