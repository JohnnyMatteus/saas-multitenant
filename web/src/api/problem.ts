import { z } from 'zod'

export const problemSchema = z
  .object({
    type: z.string().optional(),
    title: z.string().optional(),
    detail: z.string().optional(),
    status: z.number().optional(),
    errors: z.record(z.array(z.string())).optional(),
  })
  .passthrough()

export type Problem = z.infer<typeof problemSchema>

// Texto do domínio para cada "type" estável da API (Problem Details).
const MESSAGES: Record<string, string> = {
  overlapping_booking: 'Você já tem uma aula nesse horário.',
  already_booked: 'Você já está nesta aula.',
  cancellation_window_closed: 'O prazo para cancelar sem custo já passou. Fale com a recepção do estúdio.',
  session_already_started: 'Esta aula já começou.',
  booking_already_canceled: 'Esta reserva já estava cancelada.',
  forbidden: 'Seu perfil não pode fazer isso.',
  not_found: 'Não encontramos isso no seu estúdio.',
  invalid_recurrence: 'A recorrência não fecha: revise dias e período.',
  validation_failed: 'Revise os campos destacados.',
}

export function problemMessage(status: number, problem: Problem | null): string {
  const type = problem?.type?.split('/').pop() ?? ''
  if (MESSAGES[type]) return MESSAGES[type]
  if (status === 401) return 'Sua sessão expirou. Entre de novo.'
  if (status === 403) return MESSAGES.forbidden!
  if (status === 404) return MESSAGES.not_found!
  if (status === 422) return problem?.detail ?? MESSAGES.validation_failed!
  if (status >= 500) return 'O estúdio não respondeu. Tente de novo em instantes.'
  return problem?.detail ?? problem?.title ?? 'Algo não saiu como esperado.'
}
