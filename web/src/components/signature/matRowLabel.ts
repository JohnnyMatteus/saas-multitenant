export function matRowLabel(capacity: number, confirmed: number, waitlist: number, started = false): string {
  const occupied = Math.min(confirmed, capacity)
  const base = `${occupied} de ${capacity} ${capacity === 1 ? 'tapete ocupado' : 'tapetes ocupados'}`
  const queue = waitlist > 0 ? `, ${waitlist} na lista de espera` : ''
  return `${base}${queue}${started ? ', aula em andamento' : ''}`
}

export function matRowCaption(capacity: number, confirmed: number, waitlist: number, started = false): string {
  if (started) return 'em andamento'
  const free = Math.max(0, capacity - confirmed)
  if (free === 0) return waitlist > 0 ? `lotada · ${waitlist} na fila` : 'lotada'
  return free === 1 ? '1 tapete livre' : `${free} tapetes livres`
}
