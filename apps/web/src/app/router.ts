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
          name: 'products',
          meta: { title: 'Produtos' },
          component: () => import('@/pages/ProductsPage.vue'),
        },
        {
          path: 'products/new',
          name: 'products-new',
          component: () => import('@/pages/ProductFormPage.vue'),
        },
        {
          path: 'products/:id/edit',
          name: 'products-edit',
          component: () => import('@/pages/ProductFormPage.vue'),
        },
        {
          path: 'products/:id',
          name: 'products-show',
          component: () => import('@/pages/ProductDetailPage.vue'),
        },
        {
          path: 'brands',
          name: 'brands',
          meta: { title: 'Marcas' },
          component: () => import('@/pages/BrandsPage.vue'),
        },
        {
          path: 'brands/new',
          name: 'brands-new',
          component: () => import('@/pages/BrandFormPage.vue'),
        },
        {
          path: 'brands/:id/edit',
          name: 'brands-edit',
          component: () => import('@/pages/BrandFormPage.vue'),
        },
        {
          path: 'brands/:id',
          name: 'brands-show',
          component: () => import('@/pages/BrandDetailPage.vue'),
        },
        {
          path: 'product-types',
          name: 'product-types',
          meta: { title: 'Tipos' },
          component: () => import('@/pages/ProductTypesPage.vue'),
        },
        {
          path: 'product-types/new',
          name: 'product-types-new',
          component: () => import('@/pages/ProductTypeFormPage.vue'),
        },
        {
          path: 'product-types/:id/edit',
          name: 'product-types-edit',
          component: () => import('@/pages/ProductTypeFormPage.vue'),
        },
        {
          path: 'product-types/:id',
          name: 'product-types-show',
          component: () => import('@/pages/ProductTypeDetailPage.vue'),
        },
        {
          path: 'units',
          name: 'units',
          meta: { title: 'Unidades' },
          component: () => import('@/pages/UnitsPage.vue'),
        },
        {
          path: 'units/new',
          name: 'units-new',
          component: () => import('@/pages/UnitFormPage.vue'),
        },
        {
          path: 'units/:id/edit',
          name: 'units-edit',
          component: () => import('@/pages/UnitFormPage.vue'),
        },
        {
          path: 'units/:id',
          name: 'units-show',
          component: () => import('@/pages/UnitDetailPage.vue'),
        },
        {
          path: 'protocols',
          name: 'protocols',
          meta: { title: 'Protocolos' },
          component: () => import('@/pages/ProtocolsPage.vue'),
        },
        {
          path: 'protocols/new',
          name: 'protocols-new',
          component: () => import('@/pages/ProtocolFormPage.vue'),
        },
        {
          path: 'protocols/:id/edit',
          name: 'protocols-edit',
          component: () => import('@/pages/ProtocolFormPage.vue'),
        },
        {
          path: 'protocols/:id',
          name: 'protocols-show',
          component: () => import('@/pages/ProtocolDetailPage.vue'),
        },
        {
          path: 'sales',
          name: 'sales',
          meta: { title: 'Vendas' },
          component: () => import('@/pages/SalesPage.vue'),
        },
        {
          path: 'sales/new',
          name: 'sales-new',
          component: () => import('@/pages/SaleFormPage.vue'),
        },
        {
          path: 'sales/:id/edit',
          name: 'sales-edit',
          component: () => import('@/pages/SaleFormPage.vue'),
        },
        {
          path: 'sales/:id',
          name: 'sales-show',
          component: () => import('@/pages/SaleDetailPage.vue'),
        },
        {
          path: 'budgets',
          name: 'budgets',
          meta: { title: 'Orçamentos' },
          component: () => import('@/pages/BudgetsPage.vue'),
        },
        {
          path: 'appointments',
          name: 'appointments',
          meta: { title: 'Agenda' },
          component: () => import('@/pages/AppointmentsPage.vue'),
        },
        {
          path: 'appointments/new',
          name: 'appointments-new',
          component: () => import('@/pages/AppointmentFormPage.vue'),
        },
        {
          path: 'appointments/:id/consume',
          name: 'appointments-consume',
          component: () => import('@/pages/AppointmentConsumePage.vue'),
        },
        {
          path: 'appointments/:id',
          name: 'appointments-show',
          component: () => import('@/pages/AppointmentDetailPage.vue'),
        },
        {
          path: 'treatments',
          name: 'treatments',
          meta: { title: 'Tratamentos' },
          component: () => import('@/pages/TreatmentsPage.vue'),
        },
        {
          path: 'treatments/:id',
          name: 'treatments-show',
          meta: { title: 'Tratamentos' },
          component: () => import('@/pages/TreatmentDetailPage.vue'),
        },
        {
          path: 'notifications',
          meta: { title: 'Alertas' },
          component: () => import('@/pages/ComingSoonPage.vue'),
        },
        {
          path: 'metrics',
          name: 'metrics',
          meta: { title: 'Métricas', permission: 'metrics.view' },
          component: () => import('@/pages/MetricsPage.vue'),
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
