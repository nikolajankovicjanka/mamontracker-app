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
        },
        {
          path: 'vehicles',
          name: 'vehicles',
          component: PlaceholderView,
          props: { title: 'Vehicles' },
        },
        {
          path: 'gps-devices',
          name: 'gps-devices',
          component: PlaceholderView,
          props: { title: 'GPS Devices' },
        },
        {
          path: 'services',
          name: 'services',
          component: PlaceholderView,
          props: { title: 'Services' },
        },
        {
          path: 'registrations',
          name: 'registrations',
          component: PlaceholderView,
          props: { title: 'Registrations' },
        },
        {
          path: 'users',
          name: 'users',
          component: PlaceholderView,
          props: { title: 'Users' },
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

  return true
})

export default router