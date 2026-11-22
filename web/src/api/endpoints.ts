import { getBlob, getJson, sendDelete, sendJson } from './client'
import {
  bookingListSchema,
  bookingOneSchema,
  cancelResultSchema,
  classSeriesSchema,
  instructorListSchema,
  logoUploadSchema,
  sessionEditResultSchema,
  meSchema,
  sessionListSchema,
  sessionOneSchema,
  studioSettingsSchema,
  whatsAppCodeSchema,
  whatsAppLinkStatusSchema,
  type CreateSeriesInput,
  type EditSessionInput,
} from './schemas'

export const api = {
  me: async () => (await getJson('/me', meSchema)).data,
  studio: async () => (await getJson('/studio', studioSettingsSchema)).data,
  updateStudio: async (input: { name?: string; cancellation_window_minutes?: number }) =>
    (await sendJson('PATCH', '/studio', studioSettingsSchema, input)).data,
  logo: () => getBlob('/studio/logo'),
  uploadLogo: async (file: File) => {
    const form = new FormData()
    form.append('logo', file)
    return (await sendJson('POST', '/studio/logo', logoUploadSchema, form)).data
  },
  sessions: async (from: string, to: string) =>
    (await getJson('/sessions', sessionListSchema, { from, to, per_page: '100' })).data,
  session: async (id: string) => (await getJson(`/sessions/${id}`, sessionOneSchema)).data,
  roster: async (sessionId: string) => (await getJson(`/sessions/${sessionId}/bookings`, bookingListSchema)).data,
  book: async (sessionId: string) => (await sendJson('POST', `/sessions/${sessionId}/bookings`, bookingOneSchema)).data,
  cancelBooking: async (bookingId: string) => (await sendJson('POST', `/bookings/${bookingId}/cancel`, cancelResultSchema)).data,
  myBookings: async () => (await getJson('/me/bookings', bookingListSchema)).data,
  editSession: async (id: string, input: EditSessionInput) =>
    (await sendJson('PATCH', `/sessions/${id}`, sessionEditResultSchema, { ...input })).data,
  cancelSession: async (id: string, reason: string | null) =>
    (await sendJson('POST', `/sessions/${id}/cancel`, sessionOneSchema, reason ? { reason } : {})).data,
  instructors: async () => (await getJson('/instructors', instructorListSchema)).data,
  whatsAppLink: async () => (await getJson('/me/whatsapp-link', whatsAppLinkStatusSchema)).data,
  issueWhatsAppCode: async () => (await sendJson('POST', '/me/whatsapp-link/code', whatsAppCodeSchema)).data,
  revokeWhatsAppLink: () => sendDelete('/me/whatsapp-link'),
  createSeries: async (input: CreateSeriesInput) =>
    (await sendJson('POST', '/class-series', classSeriesSchema, { ...input })).data,
}
