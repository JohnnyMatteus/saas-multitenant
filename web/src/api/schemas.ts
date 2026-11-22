import { z } from 'zod'

export const roleSchema = z.enum(['studio_admin', 'instructor', 'student'])
export type Role = z.infer<typeof roleSchema>

export const studioSchema = z.object({
  id: z.string(),
  slug: z.string(),
  name: z.string(),
  timezone: z.string(),
})

export const meSchema = z.object({
  data: z.object({
    id: z.string(),
    name: z.string(),
    email: z.string().nullable(),
    role: roleSchema,
    studio: studioSchema,
  }),
})
export type Me = z.infer<typeof meSchema>['data']

export const studioSettingsSchema = z.object({
  data: studioSchema.extend({
    cancellation_window_minutes: z.number().int(),
    has_logo: z.boolean(),
  }),
})
export type StudioSettings = z.infer<typeof studioSettingsSchema>['data']

export const sessionSchema = z.object({
  id: z.string(),
  series_id: z.string().nullable(),
  title: z.string(),
  instructor: z.object({ id: z.string(), name: z.string() }),
  starts_at: z.string(),
  ends_at: z.string(),
  starts_at_local: z.string(),
  ends_at_local: z.string(),
  timezone: z.string(),
  cancellation_deadline: z.string(),
  capacity: z.number().int(),
  confirmed_count: z.number().int(),
  available_spots: z.number().int(),
  waitlist_count: z.number().int(),
  canceled_at: z.string().nullable().optional(),
  cancel_reason: z.string().nullable().optional(),
})
export type ClassSession = z.infer<typeof sessionSchema>

const pageMeta = z.object({ current_page: z.number(), last_page: z.number(), total: z.number() }).passthrough()

export const sessionListSchema = z.object({ data: z.array(sessionSchema), meta: pageMeta })
export const sessionOneSchema = z.object({ data: sessionSchema })

export const bookingStatusSchema = z.enum(['confirmed', 'waitlisted', 'canceled'])
export type BookingStatus = z.infer<typeof bookingStatusSchema>

export const bookingSchema = z.object({
  id: z.string(),
  session_id: z.string(),
  member: z.object({ id: z.string(), name: z.string() }).optional(),
  session: sessionSchema.optional(),
  status: bookingStatusSchema,
  waitlist_position: z.number().int().nullable(),
  queued_at: z.string(),
  confirmed_at: z.string().nullable(),
  canceled_at: z.string().nullable(),
})
export type Booking = z.infer<typeof bookingSchema>

export const bookingOneSchema = z.object({ data: bookingSchema })
export const bookingListSchema = z.object({ data: z.array(bookingSchema) })

export const cancelResultSchema = z.object({
  data: z.object({ canceled: bookingSchema, promoted: bookingSchema.nullable() }),
})
export type CancelResult = z.infer<typeof cancelResultSchema>['data']

export const instructorListSchema = z.object({
  data: z.array(z.object({ id: z.string(), name: z.string(), role: roleSchema })),
})
export type Instructor = z.infer<typeof instructorListSchema>['data'][number]

export const classSeriesSchema = z.object({
  data: z.object({
    id: z.string(),
    title: z.string(),
    instructor_id: z.string(),
    weekdays: z.array(z.number().int()),
    start_time: z.string(),
    duration_minutes: z.number().int(),
    capacity: z.number().int(),
    starts_on: z.string(),
    ends_on: z.string(),
    sessions_count: z.number().int().nullable(),
    rolling: z.boolean().optional(),
  }),
})

export const sessionEditResultSchema = z.object({
  data: z.object({
    series_id: z.string().nullable(),
    updated: z.array(z.string()),
    skipped: z.array(z.object({ session_id: z.string(), reasons: z.array(z.string()) })),
  }),
})
export type SessionEditResult = z.infer<typeof sessionEditResultSchema>['data']

export type EditScope = 'occurrence' | 'following' | 'all'
export interface EditSessionInput {
  scope: EditScope
  title?: string
  instructor_id?: string
  capacity?: number
  start_time?: string
  duration_minutes?: number
}

export const skipReasonLabels: Record<string, string> = {
  has_bookings: 'já tem reservas e o horário mudou',
  capacity_below_confirmed: 'a nova capacidade é menor que os confirmados',
}

export const logoUploadSchema = z.object({ data: z.object({ logo_path: z.string() }) })

export interface CreateSeriesInput {
  title: string
  instructor_id: string
  weekdays: number[]
  start_time: string
  duration_minutes: number
  capacity: number
  starts_on: string
  ends_on: string
  rolling?: boolean
}

// Reserva confirmada direto tem confirmed_at == queued_at; quem veio da fila
// foi confirmado depois, no cancelamento de outra pessoa.
export function wasPromoted(booking: Booking): boolean {
  if (booking.status !== 'confirmed' || !booking.confirmed_at) return false
  return new Date(booking.confirmed_at).getTime() - new Date(booking.queued_at).getTime() > 2000
}
