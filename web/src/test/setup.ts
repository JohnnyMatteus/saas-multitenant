import { config } from '@vue/test-utils'
import { createVuetify } from 'vuetify'

class ResizeObserverStub {
  observe() {}
  unobserve() {}
  disconnect() {}
}
globalThis.ResizeObserver ??= ResizeObserverStub as unknown as typeof ResizeObserver

config.global.plugins = [createVuetify()]
