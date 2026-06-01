import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { pinia } from '@/stores'
import AppLayout from '@/layouts/AppLayout.vue'
import LoginView from '@/views/LoginView.vue'
import DashboardView from '@/views/DashboardView.vue'
import ReportsView from '@/views/ReportsView.vue'
import VehiclesView from '@/views/VehiclesView.vue'
import VehicleShowView from '@/views/VehicleShowView.vue'
import VehicleFormView from '@/views/VehicleFormView.vue'
import GpsDevicesView from '@/views/GpsDevicesView.vue'
import GpsDeviceShowView from '@/views/GpsDeviceShowView.vue'
import GpsDeviceFormView from '@/views/GpsDeviceFormView.vue'
import ServicesView from '@/views/ServicesView.vue'
import ServiceShowView from '@/views/ServiceShowView.vue'
import ServiceFormView from '@/views/ServiceFormView.vue'
import RegistrationsView from '@/views/RegistrationsView.vue'
import RegistrationFormView from '@/views/RegistrationFormView.vue'
import UsersView from '@/views/UsersView.vue'
import UserFormView from '@/views/UserFormView.vue'
import UserShowView from '@/views/UserShowView.vue'

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
          component: ServicesView,
          meta: { feature: 'services' },
        },
        {
          path: 'services/create',
          name: 'service-create',
          component: ServiceFormView,
          meta: { feature: 'services', requiresTenantAdmin: true },
        },
        {
          path: 'services/:id',
          name: 'service-show',
          component: ServiceShowView,
          meta: { feature: 'services' },
        },
        {
          path: 'services/:id/edit',
          name: 'service-edit',
          component: ServiceFormView,
          meta: { feature: 'services', requiresTenantAdmin: true },
        },
        {
          path: 'registrations',
          name: 'registrations',
          component: RegistrationsView,
          meta: { feature: 'registrations' },
        },
        {
          path: 'registrations/:vehicleId/edit',
          name: 'registration-edit',
          component: RegistrationFormView,
          meta: { feature: 'registrations', requiresTenantAdmin: true },
        },
        {
          path: 'users',
          name: 'users',
          component: UsersView,
          meta: { feature: 'users' },
        },
        {
          path: 'users/create',
          name: 'user-create',
          component: UserFormView,
          meta: { feature: 'users', requiresTenantAdmin: true },
        },
        {
          path: 'users/:id',
          name: 'user-show',
          component: UserShowView,
          meta: { feature: 'users' },
        },
        {
          path: 'users/:id/edit',
          name: 'user-edit',
          component: UserFormView,
          meta: { feature: 'users', requiresTenantAdmin: true },
        },
        {
          path: 'reports',
          name: 'reports',
          component: ReportsView,
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