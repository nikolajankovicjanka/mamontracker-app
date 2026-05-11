import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { pinia } from '@/stores'
import AppLayout from '@/layouts/AppLayout.vue'
import LoginView from '@/views/LoginView.vue'
import DashboardView from '@/views/DashboardView.vue'
import PlaceholderView from '@/views/PlaceholderView.vue'
import VehiclesView from '@/views/VehiclesView.vue'
import VehicleShowView from '@/views/VehicleShowView.vue'
import VehicleFormView from '@/views/VehicleFormView.vue'
import GpsDevicesView from '@/views/GpsDevicesView.vue'
import GpsDeviceShowView from '@/views/GpsDeviceShowView.vue'
import GpsDeviceFormView from '@/views/GpsDeviceFormView.vue'

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
          component: VehiclesView,
          meta: { feature: 'vehicles' },
        },
        {
          path: 'vehicles/create',
          name: 'vehicle-create',
          component: VehicleFormView,
          meta: { feature: 'vehicles', requiresTenantAdmin: true },
        },
        {
          path: 'vehicles/:id',
          name: 'vehicle-show',
          component: VehicleShowView,
          meta: { feature: 'vehicles' },
        },
        {
          path: 'vehicles/:id/edit',
          name: 'vehicle-edit',
          component: VehicleFormView,
          meta: { feature: 'vehicles', requiresTenantAdmin: true },
        },
        {
          path: 'gps-devices',
          name: 'gps-devices',
          component: GpsDevicesView,
          meta: { feature: 'gps_devices' },
        },
        {
          path: 'gps-devices/create',
          name: 'gps-device-create',
          component: GpsDeviceFormView,
          meta: { feature: 'gps_devices', requiresTenantAdmin: true },
        },
        {
          path: 'gps-devices/:id',
          name: 'gps-device-show',
          component: GpsDeviceShowView,
          meta: { feature: 'gps_devices' },
        },
        {
          path: 'gps-devices/:id/edit',
          name: 'gps-device-edit',
          component: GpsDeviceFormView,
          meta: { feature: 'gps_devices', requiresTenantAdmin: true },
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

  if (to.meta.requiresTenantAdmin && auth.user?.role !== 'tenant_admin') {
    return { name: 'dashboard' }
  }

  return true
})

export default router