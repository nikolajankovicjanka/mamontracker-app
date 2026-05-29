<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
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
  CheckCheck,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useAlertsStore, type AlertItem } from '@/stores/alerts'

const auth = useAuthStore()
const alertsStore = useAlertsStore()
const router = useRouter()
const route = useRoute()

const mobileMenuOpen = ref(false)
const notificationsOpen = ref(false)
const notificationsPanel = ref<HTMLElement | null>(null)

const {
  items: alerts,
  unreadCount,
  loading: alertsLoading,
  markingAllRead,
} = storeToRefs(alertsStore)

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

const unreadBadgeLabel = computed(() => {
  if (unreadCount.value > 9) return '9+'
  return String(unreadCount.value)
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

function notificationSeverityClass(value: string) {
  if (value === 'high') return 'bg-rose-50 text-rose-700 ring-rose-200'
  if (value === 'medium') return 'bg-amber-50 text-amber-700 ring-amber-200'
  if (value === 'low') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  return 'bg-slate-100 text-slate-700 ring-slate-200'
}

function formatNotificationDate(date: string | null) {
  if (!date) return '—'

  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

async function toggleNotifications() {
  notificationsOpen.value = !notificationsOpen.value

  if (notificationsOpen.value) {
    await alertsStore.fetchAlerts('all', 8)
  }
}

async function handleAlertClick(item: AlertItem) {
  if (!item.is_read) {
    await alertsStore.markAsRead(item.id)
  }

  notificationsOpen.value = false

  if (item.route_name) {
    await router.push({
      name: item.route_name,
      params: item.route_params ?? {},
    })
  }
}

async function handleMarkAllAsRead() {
  await alertsStore.markAllAsRead()
}

function handleClickOutside(event: MouseEvent) {
  const target = event.target as Node

  if (notificationsPanel.value && !notificationsPanel.value.contains(target)) {
    notificationsOpen.value = false
  }
}

async function handleLogout() {
  mobileMenuOpen.value = false
  notificationsOpen.value = false
  alertsStore.clearAlerts()
  await auth.logout()
  await router.replace({ name: 'login' })
}

function closeMobileMenu() {
  mobileMenuOpen.value = false
}

onMounted(async () => {
  document.addEventListener('click', handleClickOutside)

  if (auth.isAuthenticated) {
    await alertsStore.fetchUnreadCount()
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
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

                <div ref="notificationsPanel" class="relative">
                  <button
                      type="button"
                      class="relative rounded-2xl border border-slate-200 bg-white p-3 text-slate-600 shadow-sm hover:bg-slate-50"
                      @click.stop="toggleNotifications"
                  >
                    <Bell class="h-5 w-5" />

                    <span
                        v-if="unreadCount > 0"
                        class="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"
                    >
      {{ unreadBadgeLabel }}
    </span>
                  </button>

                  <div
                      v-if="notificationsOpen"
                      class="fixed left-4 right-4 top-20 z-50 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl sm:absolute sm:left-auto sm:right-0 sm:top-14 sm:w-[380px]"
                  >
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                      <div>
                        <div class="text-sm font-semibold text-slate-900">Obavještenja</div>
                        <div class="text-xs text-slate-500">
                          Nepročitano: {{ unreadCount }}
                        </div>
                      </div>

                      <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                          :disabled="markingAllRead || unreadCount === 0"
                          @click.stop="handleMarkAllAsRead"
                      >
                        <CheckCheck class="h-4 w-4" />
                        <span>Označi sve</span>
                      </button>
                    </div>

                    <div v-if="alertsLoading" class="p-5 text-sm text-slate-500">
                      Učitavanje obavještenja...
                    </div>

                    <div v-else-if="!alerts.length" class="p-5 text-sm text-slate-500">
                      Nema obavještenja.
                    </div>

                    <div v-else class="max-h-[65vh] overflow-y-auto sm:max-h-[420px]">
                      <button
                          v-for="item in alerts"
                          :key="item.id"
                          type="button"
                          class="w-full border-b border-slate-100 px-5 py-4 text-left transition hover:bg-slate-50 last:border-b-0"
                          @click.stop="handleAlertClick(item)"
                      >
                        <div class="flex items-start gap-3">
                          <div
                              class="mt-0.5 rounded-full px-2.5 py-1 text-[11px] font-medium ring-1"
                              :class="notificationSeverityClass(item.severity)"
                          >
                            {{ item.type }}
                          </div>

                          <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                              <div class="font-medium text-slate-900">
                                {{ item.title }}
                              </div>

                              <span
                                  v-if="!item.is_read"
                                  class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500"
                              />
                            </div>

                            <div class="mt-1 text-sm text-slate-500">
                              {{ item.message }}
                            </div>

                            <div class="mt-2 text-xs text-slate-400">
                              {{ formatNotificationDate(item.sent_at) }}
                            </div>
                          </div>
                        </div>
                      </button>
                    </div>
                  </div>
                </div>

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