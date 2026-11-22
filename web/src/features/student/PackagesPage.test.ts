import { flushPromises, mount } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PackagesPage from './PackagesPage.vue'

const calls = vi.hoisted(() => ({
  me: vi.fn(),
  lessonPackages: vi.fn(),
  myPackageOrders: vi.fn(),
  orderPackage: vi.fn(),
}))
vi.mock('@/api/endpoints', () => ({ api: calls }))

const pkg = { id: '01HPKG', name: 'Pacote 8 aulas', lesson_count: 8, price_cents: 32000, currency: 'BRL', active: true }
const order = {
  id: '01HORD',
  payment_id: 'pay_01abc',
  package: { id: pkg.id, name: pkg.name },
  member: { id: 'm', name: 'Aluna Exemplo' },
  lesson_count: 8,
  amount_cents: 32000,
  currency: 'BRL',
  status: 'pending',
  paid_at: null,
  created_at: '2026-11-22T12:00:00Z',
}

function render() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return mount(PackagesPage, { global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } })
}

describe('PackagesPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    calls.me.mockResolvedValue({ id: '1', name: 'A', email: null, role: 'student', studio: { id: 's', slug: 'x', name: 'X', timezone: 'America/Sao_Paulo' } })
    calls.lessonPackages.mockResolvedValue([pkg])
    calls.myPackageOrders.mockResolvedValue([])
  })

  it('lista os pacotes e cria o pedido pela API, mostrando a referência de pagamento', async () => {
    calls.orderPackage.mockResolvedValue(order)
    const wrapper = render()
    await flushPromises()
    expect(wrapper.text()).toContain('Pacote 8 aulas')

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(calls.orderPackage.mock.calls[0]?.[0]).toBe('01HPKG')
    expect(wrapper.get('[data-testid="order-reference"]').text()).toContain('pay_01abc')
  })

  it('mostra o erro da API quando o pacote saiu de venda', async () => {
    const { ApiError } = await import('@/api/client')
    calls.orderPackage.mockRejectedValue(new ApiError(422, { type: 'package_unavailable', title: 'x', status: 422, detail: 'Este pacote não está à venda.' }))
    const wrapper = render()
    await flushPromises()

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Este pacote não está à venda.')
    expect(wrapper.find('[data-testid="order-reference"]').exists()).toBe(false)
  })

  it('mostra o estado de cada pedido', async () => {
    calls.myPackageOrders.mockResolvedValue([order, { ...order, id: '2', payment_id: 'pay_02', status: 'paid', paid_at: '2026-11-22T13:00:00Z' }])
    const wrapper = render()
    await flushPromises()

    expect(wrapper.find('[data-testid="status-pending"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="status-paid"]').exists()).toBe(true)
  })
})
