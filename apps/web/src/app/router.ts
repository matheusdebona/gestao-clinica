import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/dev/ui',
    },
    {
      path: '/dev/ui',
      name: 'dev-ui',
      component: () => import('@/pages/DevUiPage.vue'),
    },
  ],
})

export default router
