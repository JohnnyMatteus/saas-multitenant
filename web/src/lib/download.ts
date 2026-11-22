/** Nome do arquivo no Content-Disposition (só o formato `filename="..."` que a API envia). */
export function filenameFrom(disposition: string | null, fallback: string): string {
  const match = /filename="([^"\\/]+)"/.exec(disposition ?? '')
  return match?.[1] ?? fallback
}

/** Entrega um Blob ao usuário como download, sem navegar. */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
