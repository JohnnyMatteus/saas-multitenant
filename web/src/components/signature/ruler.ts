export const RULER_START_HOUR = 6
export const RULER_END_HOUR = 22

/** Posição (0–100) de um minuto do dia na régua das 6h às 22h; fora dela, null. */
export function rulerPercent(minutesOfDay: number): number | null {
  const start = RULER_START_HOUR * 60
  const end = RULER_END_HOUR * 60
  if (minutesOfDay < start || minutesOfDay > end) return null
  return ((minutesOfDay - start) / (end - start)) * 100
}

export function clampPercent(minutesOfDay: number): number {
  const start = RULER_START_HOUR * 60
  const end = RULER_END_HOUR * 60
  return ((Math.min(Math.max(minutesOfDay, start), end) - start) / (end - start)) * 100
}
