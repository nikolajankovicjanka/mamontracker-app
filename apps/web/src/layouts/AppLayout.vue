<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const mobileMenuOpen = ref(false)

const tenantName = computed(() => auth.tenant?.name ?? 'Tenant')

const navigation = computed(() => {
  const features = auth.tenant?.features ?? {}

  return [
    { name: 'dashboard', label: 'Dashboard', to: '/', feature: 'dashboard' },
    { name: 'vehicles', label: 'Vehicles', to: '/vehicles', feature: 'vehicles' },
    { name: 'gps-devices', label: 'GPS Devices', to: '/gps-devices', feature: 'gps_devices' },
    { name: 'services', label: 'Services', to: '/services', feature: 'services' },
    { name: 'registrations', label: 'Registrations', to: '/registrations', feature: 'registrations' },
    { name: 'users', label: 'Users', to: '/users', feature: 'users' },
    { name: 'reports', label: 'Reports', to: '/reports', feature: 'reports' },
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
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="flex min-h-screen">
      <aside
          class="fixed inset-y-0 left-0 z-40 w-72 border-r border-slate-200 bg-white p-6 transition-transform duration-200 xl:static xl:translate-x-0"
          :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full xl:translate-x-0'"
      >
        <div class="mb-8 flex items-center justify-between xl:block">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
              Mamontrucker
            </div>
            <div class="mt-2 text-xl font-bold">
              {{ tenantName }}
            </div>
          </div>

          <button
              type="button"
              class="rounded-xl border border-slate-200 px-3 py-2 text-sm xl:hidden"
              @click="closeMobileMenu"
          >
            Close
          </button>
        </div>

        <nav class="space-y-2">
          <RouterLink
              v-for="item in navigation"
              :key="item.name"
              :to="item.to"
              class="block rounded-xl px-4 py-3 text-sm font-medium transition"
              :class="isActive(item.to)
              ? 'bg-slate-900 text-white'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
              @click="closeMobileMenu"
          >
            {{ item.label }}
          </RouterLink>
        </nav>

        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <div class="text-sm text-slate-500">Logged in as</div>
          <div class="mt-1 font-semibold text-slate-900">
            {{ auth.user?.name }}
          </div>
          <div class="mt-1 text-xs uppercase tracking-wide text-slate-400">
            {{ auth.user?.role }}
          </div>
        </div>
      </aside>

      <div
          v-if="mobileMenuOpen"
          class="fixed inset-0 z-30 bg-slate-900/30 xl:hidden"
          @click="closeMobileMenu"
      />

      <div class="flex min-w-0 flex-1 flex-col">
        <header class="border-b border-slate-200 bg-white px-4 py-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <button
                  type="button"
                  class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium xl:hidden"
                  @click="mobileMenuOpen = true"
              >
                Menu
              </button>

              <div>
                <div class="text-sm text-slate-500">Tenant</div>
                <div class="font-semibold text-slate-900">
                  {{ tenantName }}
                </div>
              </div>
            </div>

            <button
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100"
                type="button"
                @click="handleLogout"
            >
              Logout
            </button>
          </div>
        </header>

        <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
          <RouterView />
        </main>
      </div>
    </div>
  </div>
</template>