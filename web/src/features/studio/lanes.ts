export interface Span {
  start: number
  end: number
}

/** Distribui aulas que se sobrepõem no mesmo dia em faixas lado a lado. */
export function assignLanes<T extends Span>(items: T[]): (T & { lane: number; lanes: number })[] {
  const sorted = [...items].sort((a, b) => a.start - b.start || a.end - b.end)
  const out: (T & { lane: number; lanes: number })[] = []
  let cluster: (T & { lane: number; lanes: number })[] = []
  let clusterEnd = -Infinity
  const flush = () => {
    const lanes = Math.max(1, ...cluster.map((c) => c.lane + 1))
    cluster.forEach((c) => (c.lanes = lanes))
    out.push(...cluster)
    cluster = []
  }
  for (const item of sorted) {
    if (item.start >= clusterEnd && cluster.length) flush()
    const used = new Set(cluster.filter((c) => c.end > item.start).map((c) => c.lane))
    let lane = 0
    while (used.has(lane)) lane++
    cluster.push({ ...item, lane, lanes: 1 })
    clusterEnd = Math.max(clusterEnd, item.end)
  }
  if (cluster.length) flush()
  return out
}
