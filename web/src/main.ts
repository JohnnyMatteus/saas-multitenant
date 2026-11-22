import '@fontsource-variable/fraunces/full.css'
import '@fontsource-variable/figtree/index.css'
import './styles/global.scss'

import { VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import { queryClient } from './plugins/query'
import vuetify from './plugins/vuetify'
import router from './router'

createApp(App).use(createPinia()).use(vuetify).use(VueQueryPlugin, { queryClient }).use(router).mount('#app')
