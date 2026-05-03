import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { pinia } from '@/stores'
import AppLayout from '@/layouts/AppLayout.vue'
import LoginView from '@/views/LoginView.vue'
import DashboardView from '@/views/DashboardView.vue'
import PlaceholderView from '@/views/PlaceholderView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: DashboardView,
          meta: { feature: 'dashboard' },
        },
        {
          path: 'vehicles',
          name: 'vehicles',
          component: PlaceholderView,
          props: { title: 'Vehicles' },
          meta: { feature: 'vehicles' },
        },
        {
          path: 'gps-devices',
          name: 'gps-devices',
          component: PlaceholderView,
          props: { title: 'GPS Devices' },
          meta: { feature: 'gps_devices' },
        },
        {
          path: 'services',
          name: 'services',
          component: PlaceholderView,
          props: { title: 'Services' },
          meta: { feature: 'services' },
        },
        {
          path: 'registrations',
          name: 'registrations',
          component: PlaceholderView,
          props: { title: 'Registrations' },
          meta: { feature: 'registrations' },
        },
        {
          path: 'users',
          name: 'users',
          component: PlaceholderView,
          props: { title: 'Users' },
          meta: { feature: 'users' },
        },
        {
          path: 'reports',
          name: 'reports',
          component: PlaceholderView,
          props: { title: 'Reports' },
          meta: { feature: 'reports' },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore(pinia)

  if (!auth.initialized) {
    await auth.init()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  const requiredFeature = to.meta.feature as string | undefined

  if (requiredFeature) {
    const features = auth.tenant?.features ?? {}

    if (!features[requiredFeature]) {
      return { name: 'dashboard' }
    }
  }

  return true
})

export default router