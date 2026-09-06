import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '@/lib/auth-storage'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/AuthPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/components/patterns/ClinicShell.vue'),
      meta: { auth: true },
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/pages/DashboardPage.vue'),
        },
        {
          path: 'clients',
          name: 'clients',
          meta: { title: 'Clientes' },
          component: () => import('@/pages/ClientsPage.vue'),
        },
        {
          path: 'clients/new',
          name: 'clients-new',
          component: () => import('@/pages/ClientFormPage.vue'),
        },
        {
          path: 'clients/:id/edit',
          name: 'clients-edit',
          component: () => import('@/pages/ClientFormPage.vue'),
        },
        {
          path: 'clients/:id',
          name: 'clients-show',
          component: () => import('@/pages/ClientDetailPage.vue'),
        },
        {
          path: 'users',
          name: 'users',
          meta: { title: 'Equipe' },
          component: () => import('@/pages/UsersPage.vue'),
        },
        {
          path: 'users/new',
          name: 'users-new',
          component: () => import('@/pages/UserFormPage.vue'),
        },
        {
          path: 'users/:id/edit',
          name: 'users-edit',
          component: () => import('@/pages/UserFormPage.vue'),
        },
        {
          path: 'users/:id',
          name: 'users-show',
          component: () => import('@/pages/UserDetailPage.vue'),
        },
        {
          path: 'products',
          meta: { title: 'Produtos' },
          component: () => import('@/pages/ComingSoonPage.vue'),
        },
        {
          path: 'sales',
          meta: { title: 'Vendas' },
          component: () => import('@/pages/ComingSoonPage.vue'),
        },
        {
          path: 'treatments',
          meta: { title: 'Tratamentos' },
          component: () => import('@/pages/ComingSoonPage.vue'),
        },
        {
          path: 'notifications',
          meta: { title: 'Alertas' },
          component: () => import('@/pages/ComingSoonPage.vue'),
        },
        {
          path: 'metrics',
          meta: { title: 'Métricas' },
          component: () => import('@/pages/ComingSoonPage.vue'),
        },
      ],
    },
    {
      path: '/dev/ui',
      name: 'dev-ui',
      component: () => import('@/pages/DevUiPage.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.ready) {
    await auth.hydrate()
  }

  const hasToken = Boolean(getToken())

  if (to.meta.auth && !hasToken) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && hasToken) {
    return { path: '/' }
  }

  return true
})

export default router
