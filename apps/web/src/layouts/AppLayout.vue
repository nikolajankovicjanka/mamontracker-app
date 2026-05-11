<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard,
  CarFront,
  Radar,
  Wrench,
  FileText,
  Users,
  BarChart3,
  Settings,
  LifeBuoy,
  Search,
  Bell,
  Menu,
  X,
  LogOut,
  ChevronRight,
  Truck,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const mobileMenuOpen = ref(false)

const tenantName = computed(() => auth.tenant?.name ?? 'Tenant')

const tenantPlanLabel = computed(() => {
  const plan = auth.tenant?.plan

  if (!plan) return 'Basic'

  if (plan === 'enterprise') return 'Enterprise'
  if (plan === 'pro') return 'Pro'

  return 'Basic'
})

const userRoleLabel = computed(() => {
  if (auth.user?.role === 'tenant_admin') return 'Tenant Admin'
  if (auth.user?.role === 'tenant_user') return 'Tenant User'
  return auth.user?.role ?? 'User'
})

const userInitials = computed(() => {
  const name = auth.user?.name?.trim()

  if (!name) return 'MT'

  return name
      .split(' ')
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join('')
})

const navigation = computed(() => {
  const features = auth.tenant?.features ?? {}

  return [
    { name: 'dashboard', label: 'Dashboard', to: '/', feature: 'dashboard', icon: LayoutDashboard },
    { name: 'vehicles', label: 'Vehicles', to: '/vehicles', feature: 'vehicles', icon: CarFront },
    { name: 'gps-devices', label: 'GPS Devices', to: '/gps-devices', feature: 'gps_devices', icon: Radar },
    { name: 'services', label: 'Services', to: '/services', feature: 'services', icon: Wrench },
    { name: 'registrations', label: 'Registrations', to: '/registrations', feature: 'registrations', icon: FileText },
    { name: 'users', label: 'Users', to: '/users', feature: 'users', icon: Users },
    { name: 'reports', label: 'Reports', to: '/reports', feature: 'reports', icon: BarChart3 },
  ].filter((item) => !!features[item.feature])
})

function isActive(path: string) {
  if (path === '/') {
    return route.path === '/'
  }

  return route.path.startsWith(path)
}

async function handleLogout() {
  mobileMenuOpen.value = false
  await auth.logout()
  await router.replace({ name: 'login' })
}

function closeMobileMenu() {
  mobileMenuOpen.value = false
}
</script>

<template>
  <div class="min-h-screen bg-[#f7f9fc] text-slate-900">
    <div class="flex min-h-screen">
      <aside
          class="fixed inset-y-0 left-0 z-40 flex w-[290px] flex-col border-r border-slate-200 bg-white/95 backdrop-blur transition-transform duration-300 xl:static xl:translate-x-0"
          :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full xl:translate-x-0'"
      >
        <div class="border-b border-slate-200 px-6 py-5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-sm">
                <Truck class="h-6 w-6" />
              </div>

              <div>
                <div class="text-lg font-bold tracking-tight text-slate-900">
                  Mamontrucker
                </div>
                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                  Fleet Platform
                </div>
              </div>
            </div>

            <button
                type="button"
                class="rounded-xl border border-slate-200 p-2 text-slate-500 hover:bg-slate-100 xl:hidden"
                @click="closeMobileMenu"
            >
              <X class="h-5 w-5" />
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-5">
          <nav class="space-y-1.5">
            <RouterLink
                v-for="item in navigation"
                :key="item.name"
                :to="item.to"
                class="group flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium transition-all duration-200"
                :class="isActive(item.to)
                ? 'bg-blue-50 text-blue-700 shadow-sm ring-1 ring-blue-100'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                @click="closeMobileMenu"
            >
              <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-xl transition"
                    :class="isActive(item.to)
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200 group-hover:text-slate-700'"
                >
                  <component :is="item.icon" class="h-4.5 w-4.5" />
                </div>

                <span>{{ item.label }}</span>
              </div>

              <ChevronRight
                  class="h-4 w-4 transition"
                  :class="isActive(item.to) ? 'text-blue-500' : 'text-slate-300 group-hover:text-slate-400'"
              />
            </RouterLink>
          </nav>

          <div class="mt-8 border-t border-slate-200 pt-6">
            <div class="mb-3 px-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
              System
            </div>

            <div class="space-y-1.5">
              <button
                  type="button"
                  class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
              >
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                  <Settings class="h-4.5 w-4.5" />
                </div>
                <span>Settings</span>
              </button>

              <button
                  type="button"
                  class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
              >
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                  <LifeBuoy class="h-4.5 w-4.5" />
                </div>
                <span>Support</span>
              </button>
            </div>
          </div>
        </div>

        <div class="border-t border-slate-200 p-4">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-sm font-bold text-white">
                {{ userInitials }}
              </div>

              <div class="min-w-0 flex-1">
                <div class="truncate font-semibold text-slate-900">
                  {{ auth.user?.name }}
                </div>
                <div class="mt-0.5 text-xs text-slate-500">
                  {{ userRoleLabel }}
                </div>
              </div>

              <button
                  type="button"
                  class="rounded-xl border border-slate-200 p-2 text-slate-500 hover:bg-white hover:text-slate-700"
                  @click="handleLogout"
              >
                <LogOut class="h-4.5 w-4.5" />
              </button>
            </div>
          </div>
        </div>
      </aside>

      <div
          v-if="mobileMenuOpen"
          class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-[2px] xl:hidden"
          @click="closeMobileMenu"
      />

      <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
          <div class="px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div class="flex items-center gap-3 lg:min-w-[420px] lg:flex-1">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-200 bg-white p-3 text-slate-600 shadow-sm hover:bg-slate-50 xl:hidden"
                    @click="mobileMenuOpen = true"
                >
                  <Menu class="h-5 w-5" />
                </button>

                <div class="relative hidden w-full max-w-md lg:block">
                  <Search class="pointer-events-none absolute left-4 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-slate-400" />
                  <input
                      type="text"
                      placeholder="Search vehicles, drivers..."
                      class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-11 pr-4 text-sm text-slate-700 outline-none transition focus:border-blue-300 focus:bg-white"
                  />
                </div>
              </div>

              <div class="flex items-center justify-between gap-3 lg:justify-end">
                <div class="hidden items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 sm:flex">
                  <span class="h-2 w-2 rounded-full bg-emerald-500" />
                  <span>System Online</span>
                </div>

                <button
                    type="button"
                    class="relative rounded-2xl border border-slate-200 bg-white p-3 text-slate-600 shadow-sm hover:bg-slate-50"
                >
                  <Bell class="h-5 w-5" />
                  <span class="absolute right-2.5 top-2.5 h-2 w-2 rounded-full bg-rose-500" />
                </button>

                <div class="hidden h-10 w-px bg-slate-200 lg:block" />

                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                  <div class="text-right">
                    <div class="text-sm font-semibold text-slate-900">
                      {{ tenantName }}
                    </div>
                    <div class="text-xs text-slate-500">
                      {{ tenantPlanLabel }} plan
                    </div>
                  </div>

                  <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-sm font-bold text-white">
                    {{ userInitials }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </header>

        <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
          <RouterView />
        </main>
      </div>
    </div>
  </div>
</template>