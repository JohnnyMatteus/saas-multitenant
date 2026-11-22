const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/** Valores trafegam em centavos inteiros; só a tela formata. */
export function formatBRL(cents: number): string {
  return brl.format(cents / 100)
}

export type ReaisParse = { cents: number } | { error: string }

const HELP = 'Use o formato 320 ou 320,50 (vírgula nos centavos).'

/**
 * Lê um valor em reais digitado no formato brasileiro. Nada é adivinhado:
 * - vírgula separa os centavos (1 ou 2 dígitos);
 * - ponto só vale como separador de milhar, em grupos de exatamente 3 dígitos (1.320 = R$ 1.320,00);
 * - "320.50" e "1.5" seriam centavos no formato americano e milhares errados no nosso: recusados.
 */
export function parseReais(text: string): ReaisParse {
  const value = text.trim()
  if (value === '') return { error: 'Informe o valor.' }

  const match = /^(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?$/.exec(value)
  if (!match) {
    return { error: /\.\d{1,2}(,|$)/.test(value) || /\.\d{4,}/.test(value) ? `O ponto não separa centavos. ${HELP}` : `Valor não reconhecido. ${HELP}` }
  }

  const reais = Number((match[1] ?? '').replace(/\./g, ''))
  const centsPart = match[2] ?? ''
  const cents = reais * 100 + Number(centsPart.padEnd(2, '0'))
  if (!Number.isSafeInteger(cents) || cents <= 0) return { error: 'O valor precisa ser maior que zero.' }
  return { cents }
}

/** Atalho: centavos ou null quando o texto não é um valor válido. */
export function parseReaisToCents(text: string): number | null {
  const parsed = parseReais(text)
  return 'cents' in parsed ? parsed.cents : null
}
