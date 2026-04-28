<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const tenantName = computed(() => auth.tenant?.name ?? 'Tenant')

async function handleLogout() {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="flex min-h-screen">
      <aside class="w-72 border-r border-slate-200 bg-white p-6">
        <div class="mb-8">
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
            Mamontrucker
          </div>
          <div class="mt-2 text-xl font-bold">
            {{ tenantName }}
          </div>
        </div>

        <nav class="space-y-2">
          <RouterLink class="block rounded-xl px-4 py-3 hover:bg-slate-100" to="/">
            Dashboard
          </RouterLink>
          <RouterLink class="block rounded-xl px-4 py-3 hover:bg-slate-100" to="/vehicles">
            Vehicles
          </RouterLink>
          <RouterLink class="block rounded-xl px-4 py-3 hover:bg-slate-100" to="/gps-devices">
            GPS Devices
          </RouterLink>
          <RouterLink class="block rounded-xl px-4 py-3 hover:bg-slate-100" to="/services">
            Services
          </RouterLink>
          <RouterLink class="block rounded-xl px-4 py-3 hover:bg-slate-100" to="/registrations">
            Registrations
          </RouterLink>
          <RouterLink class="block rounded-xl px-4 py-3 hover:bg-slate-100" to="/users">
            Users
          </RouterLink>
        </nav>
      </aside>

      <div class="flex-1">
        <header class="border-b border-slate-200 bg-white px-8 py-5">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm text-slate-500">Logged in as</div>
              <div class="font-semibold">
                {{ auth.user?.name }} · {{ auth.user?.role }}
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

        <main class="p-8">
          <RouterView />
        </main>
      </div>
    </div>
  </div>
</template>