import { flushPromises, mount } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WhatsAppLinkCard from './WhatsAppLinkCard.vue'

const calls = vi.hoisted(() => ({
  whatsAppLink: vi.fn(),
  issueWhatsAppCode: vi.fn(),
  revokeWhatsAppLink: vi.fn(),
}))
vi.mock('@/api/endpoints', () => ({ api: calls }))

function render() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return mount(WhatsAppLinkCard, { props: { timezone: 'America/Sao_Paulo' }, global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } })
}

describe('WhatsAppLinkCard', () => {
  beforeEach(() => vi.resetAllMocks())

  it('gera o código pela API e o mostra ao aluno', async () => {
    calls.whatsAppLink.mockResolvedValue({ linked: false, linked_at: null, code_pending: false })
    calls.issueWhatsAppCode.mockResolvedValue({ code: '123456', expires_at: '2026-11-22T12:10:00Z' })
    const wrapper = render()
    await flushPromises()

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(calls.issueWhatsAppCode).toHaveBeenCalledOnce()
    expect(wrapper.get('[data-testid="wa-code"]').text()).toContain('123456')
  })

  it('mostra o vínculo e desvincula pela API', async () => {
    calls.whatsAppLink.mockResolvedValue({ linked: true, linked_at: '2026-11-20T15:00:00Z', code_pending: false })
    calls.revokeWhatsAppLink.mockResolvedValue(undefined)
    const wrapper = render()
    await flushPromises()

    expect(wrapper.get('[data-testid="wa-linked"]').text()).toContain('20/11/2026 12:00')
    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(calls.revokeWhatsAppLink).toHaveBeenCalledOnce()
  })
})
