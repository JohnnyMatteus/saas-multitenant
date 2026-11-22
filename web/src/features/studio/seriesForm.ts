import { z } from 'zod'

export const MAX_SPAN_DAYS = 183

export const seriesFormSchema = z
  .object({
    title: z.string().trim().min(2, 'Dê um nome à aula').max(120, 'Nome longo demais'),
    instructor_id: z.string({ required_error: 'Escolha quem dá a aula' }).min(1, 'Escolha quem dá a aula'),
    capacity: z.coerce.number({ invalid_type_error: 'Quantos tapetes?' }).int().min(1, 'Pelo menos 1 tapete').max(200, 'No máximo 200 tapetes'),
    weekdays: z.array(z.number().int().min(1).max(7)).min(1, 'Escolha ao menos um dia'),
    start_time: z.string().regex(/^\d{2}:\d{2}$/, 'Horário no formato 07:00'),
    duration_minutes: z.coerce.number().int().min(15).max(240),
    starts_on: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data inicial'),
    ends_on: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data final'),
  })
  .refine((v) => v.ends_on >= v.starts_on, { path: ['ends_on'], message: 'A data final vem depois da inicial' })
  .refine((v) => (Date.parse(v.ends_on) - Date.parse(v.starts_on)) / 86_400_000 <= MAX_SPAN_DAYS, {
    path: ['ends_on'],
    message: 'Período de até 6 meses por vez',
  })

export type SeriesForm = z.infer<typeof seriesFormSchema>

export const WEEKDAYS = [
  { value: 1, short: 'seg', long: 'segunda' },
  { value: 2, short: 'ter', long: 'terça' },
  { value: 3, short: 'qua', long: 'quarta' },
  { value: 4, short: 'qui', long: 'quinta' },
  { value: 5, short: 'sex', long: 'sexta' },
  { value: 6, short: 'sáb', long: 'sábado' },
  { value: 7, short: 'dom', long: 'domingo' },
]
