import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, onScopeDispose, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { api } from './endpoints'
import type { EditSessionInput } from './schemas'

export const keys = {
  me: ['me'] as const,
  studio: ['studio'] as const,
  logo: ['studio', 'logo'] as const,
  sessions: (from: string, to: string) => ['sessions', from, to] as const,
  session: (id: string) => ['session', id] as const,
  roster: (id: string) => ['roster', id] as const,
  myBookings: ['my-bookings'] as const,
  instructors: ['instructors'] as const,
}

export function useMe() {
  return useQuery({ queryKey: keys.me, queryFn: api.me, staleTime: 5 * 60_000 })
}

export function useStudio() {
  return useQuery({ queryKey: keys.studio, queryFn: api.studio, staleTime: 60_000 })
}

export function useSessions(range: MaybeRefOrGetter<{ from: string; to: string } | null>, refetchMs?: number) {
  return useQuery({
    queryKey: computed(() => {
      const r = toValue(range)
      return keys.sessions(r?.from ?? '', r?.to ?? '')
    }),
    queryFn: () => {
      const r = toValue(range)!
      return api.sessions(r.from, r.to)
    },
    enabled: computed(() => toValue(range) !== null),
    refetchInterval: refetchMs,
  })
}

export function useSession(id: MaybeRefOrGetter<string>) {
  return useQuery({ queryKey: computed(() => keys.session(toValue(id))), queryFn: () => api.session(toValue(id)), refetchInterval: 5000 })
}

export function useRoster(id: MaybeRefOrGetter<string>, enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => keys.roster(toValue(id))),
    queryFn: () => api.roster(toValue(id)),
    enabled: computed(() => toValue(enabled)),
    // A lista fica aberta no balcão: acompanha cancelamentos e promoções sem recarregar.
    refetchInterval: 5000,
  })
}

export function useMyBookings() {
  return useQuery({ queryKey: keys.myBookings, queryFn: api.myBookings, refetchInterval: 15_000 })
}

export function useInstructors(enabled: MaybeRefOrGetter<boolean>) {
  return useQuery({ queryKey: keys.instructors, queryFn: api.instructors, enabled: computed(() => toValue(enabled)) })
}

function useInvalidateSchedule() {
  const client = useQueryClient()
  return () =>
    Promise.all([
      client.invalidateQueries({ queryKey: ['sessions'] }),
      client.invalidateQueries({ queryKey: ['session'] }),
      client.invalidateQueries({ queryKey: ['roster'] }),
      client.invalidateQueries({ queryKey: keys.myBookings }),
    ])
}

export function useBook() {
  const invalidate = useInvalidateSchedule()
  return useMutation({ mutationFn: api.book, onSettled: invalidate })
}

export function useCancelBooking() {
  const invalidate = useInvalidateSchedule()
  return useMutation({ mutationFn: api.cancelBooking, onSettled: invalidate })
}

export function useEditSession() {
  const invalidate = useInvalidateSchedule()
  return useMutation({
    mutationFn: ({ id, input }: { id: string; input: EditSessionInput }) => api.editSession(id, input),
    onSuccess: invalidate,
  })
}

export function useCancelSession() {
  const invalidate = useInvalidateSchedule()
  return useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string | null }) => api.cancelSession(id, reason),
    onSuccess: invalidate,
  })
}

export function useCreateSeries() {
  const invalidate = useInvalidateSchedule()
  return useMutation({ mutationFn: api.createSeries, onSuccess: invalidate })
}

export function useUpdateStudio() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: api.updateStudio,
    onSuccess: (studio) => {
      client.setQueryData(keys.studio, studio)
      void client.invalidateQueries({ queryKey: keys.me })
      // O prazo de cancelamento de cada aula depende da janela do estúdio.
      void client.invalidateQueries({ queryKey: ['sessions'] })
    },
  })
}

export function useUploadLogo() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: api.uploadLogo,
    onSuccess: () => Promise.all([client.invalidateQueries({ queryKey: keys.logo }), client.invalidateQueries({ queryKey: keys.studio })]),
  })
}

// O logo exige o Bearer, então não dá para usar a URL direto num <img>.
export function useLogoUrl() {
  const query = useQuery({ queryKey: keys.logo, queryFn: api.logo, staleTime: 5 * 60_000 })
  const url = ref<string | null>(null)
  watch(
    () => query.data.value,
    (blob) => {
      if (url.value) URL.revokeObjectURL(url.value)
      url.value = blob ? URL.createObjectURL(blob) : null
    },
    { immediate: true },
  )
  onScopeDispose(() => {
    if (url.value) URL.revokeObjectURL(url.value)
  })
  return url
}
