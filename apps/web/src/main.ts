import { VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from '@/app/App.vue'
import router from '@/app/router'
import { queryClient } from '@/lib/query-client'
import '@/style.css'

const app = createApp(App)
app.use(createPinia())
app.use(VueQueryPlugin, { queryClient })
app.use(router)
app.mount('#app')
