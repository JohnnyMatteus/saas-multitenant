import { FetchError, ofetch } from 'ofetch'
import type { z } from 'zod'
import { accessToken, login } from '@/auth/oidc'
import { problemMessage, problemSchema, type Problem } from './problem'

export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly problem: Problem | null,
  ) {
    super(problemMessage(status, problem))
    this.name = 'ApiError'
  }

  get type(): string | null {
    return this.problem?.type ?? null
  }
}

const http = ofetch.create({
  baseURL: '/api/v1',
  headers: { Accept: 'application/json' },
  async onRequest({ options }) {
    const token = await accessToken()
    if (token) {
      const headers = new Headers(options.headers)
      headers.set('Authorization', `Bearer ${token}`)
      options.headers = headers
    }
  },
})

async function call<T>(run: () => Promise<T>): Promise<T> {
  try {
    return await run()
  } catch (error) {
    if (error instanceof FetchError && error.response) {
      const parsed = problemSchema.safeParse(error.data)
      if (error.response.status === 401) {
        void login(window.location.pathname)
      }
      throw new ApiError(error.response.status, parsed.success ? parsed.data : null)
    }
    throw error
  }
}

// Toda resposta passa pelo schema: se a API mudar o contrato, a tela falha
// aqui, com mensagem clara, e não três componentes depois.
export function getJson<S extends z.ZodTypeAny>(path: string, schema: S, query?: Record<string, string>): Promise<z.output<S>> {
  return call(async () => schema.parse(await http(path, { query })))
}

export function sendJson<S extends z.ZodTypeAny>(
  method: 'POST' | 'PATCH',
  path: string,
  schema: S,
  body?: Record<string, unknown> | FormData,
): Promise<z.output<S>> {
  return call(async () => schema.parse(await http(path, { method, body })))
}

export function getBlob(path: string): Promise<Blob | null> {
  return call(async () => {
    try {
      return await http(path, { responseType: 'blob' })
    } catch (error) {
      if (error instanceof FetchError && error.response?.status === 404) return null
      throw error
    }
  })
}
