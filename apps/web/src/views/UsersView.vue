<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { AxiosError } from 'axios'
import { useRouter } from 'vue-router'
import { useUsersStore } from '@/stores/users'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const usersStore = useUsersStore()

const {
  items,
  loading,
  page,
  lastPage,
  total,
  search,
  role,
  status,
  hasItems,
  updatingStatus,
} = storeToRefs(usersStore)

const canManage = computed(() => auth.user?.role === 'tenant_admin')

const roleOptions = [
  { label: 'Sve role', value: '' },
  { label: 'Tenant admin', value: 'tenant_admin' },
  { label: 'Tenant user', value: 'tenant_user' },
]

const statusOptions = [
  { label: 'Svi statusi', value: '' },
  { label: 'Aktivni', value: 'active' },
  { label: 'Neaktivni', value: 'inactive' },
]

const from = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * usersStore.perPage + 1
})

const to = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * usersStore.perPage + items.value.length
})

onMounted(async () => {
  await usersStore.fetchUsers()
})

function roleLabel(value: string) {
  if (value === 'tenant_admin') return 'Tenant admin'
  if (value === 'tenant_user') return 'Tenant user'
  return value
}

function statusBadgeClass(isActive: boolean) {
  return isActive
      ? 'bg-emerald-50 text-emerald-700'
      : 'bg-slate-100 text-slate-700'
}

function formatDateTime(value: string | null) {
  if (!value) return '—'

  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

async function toggleUserStatus(id: number, nextValue: boolean) {
  try {
    await usersStore.updateUserStatus(id, nextValue)
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    alert(axiosError.response?.data?.message ?? 'Status korisnika nije moguće promijeniti.')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          Korisnici
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Upravljanje korisnicima firme, rolama i privilegijama.
        </p>
      </div>

      <div v-if="canManage">
        <button
            type="button"
            class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800"
            @click="router.push({ name: 'user-create' })"
        >
          Dodaj korisnika
        </button>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_220px_220px_auto]">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Pretraga</label>
          <input
              v-model="search"
              type="text"
              placeholder="Ime ili email"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @keyup.enter="usersStore.applyFilters()"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Rola</label>
          <select
              v-model="role"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @change="usersStore.applyFilters()"
          >
            <option
                v-for="option in roleOptions"
                :key="option.value"
                :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
          <select
              v-model="status"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @change="usersStore.applyFilters()"
          >
            <option
                v-for="option in statusOptions"
                :key="option.value"
                :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="flex items-end">
          <button
              type="button"
              class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 lg:w-auto"
              @click="usersStore.applyFilters()"
          >
            Primijeni filtere
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Lista korisnika</h2>
        <div class="text-sm text-slate-500">
          Prikazano {{ from }}–{{ to }} od {{ total }}
        </div>
      </div>

      <div
          v-if="loading"
          class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        Učitavanje korisnika...
      </div>

      <div
          v-else-if="!hasItems"
          class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        Nema korisnika za odabrane filtere.
      </div>

      <template v-else>
        <div class="hidden overflow-x-auto xl:block">
          <table class="min-w-full text-left">
            <thead>
            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <th class="pb-3 font-medium">Korisnik</th>
              <th class="pb-3 font-medium">Rola</th>
              <th class="pb-3 font-medium">Status</th>
              <th class="pb-3 font-medium">Aktivna zaduženja</th>
              <th class="pb-3 font-medium">Posljednji login</th>
              <th class="pb-3 font-medium">Akcije</th>
            </tr>
            </thead>
            <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
                class="border-b border-slate-100 last:border-b-0"
            >
              <td class="py-4">
                <div class="font-semibold text-slate-900">{{ item.name }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ item.email }}</div>
              </td>

              <td class="py-4 text-slate-700">
                {{ roleLabel(item.role) }}
              </td>

              <td class="py-4">
                  <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="statusBadgeClass(item.is_active)"
                  >
                    {{ item.is_active ? 'Aktivan' : 'Neaktivan' }}
                  </span>
              </td>

              <td class="py-4 text-slate-700">
                {{ item.active_vehicle_assignments_count ?? 0 }}
              </td>

              <td class="py-4 text-slate-600">
                {{ formatDateTime(item.last_login_at) }}
              </td>

              <td class="py-4">
                <div class="flex flex-wrap gap-2">
                  <button
                      type="button"
                      class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                      @click="router.push({ name: 'user-show', params: { id: item.id } })"
                  >
                    Pregled
                  </button>

                  <button
                      v-if="canManage"
                      type="button"
                      class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                      @click="router.push({ name: 'user-edit', params: { id: item.id } })"
                  >
                    Uredi
                  </button>

                  <button
                      v-if="canManage"
                      type="button"
                      class="rounded-xl px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                      :class="item.is_active ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                      :disabled="updatingStatus"
                      @click="toggleUserStatus(item.id, !item.is_active)"
                  >
                    {{ item.is_active ? 'Deaktiviraj' : 'Aktiviraj' }}
                  </button>
                </div>
              </td>
            </tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-3 xl:hidden">
          <div
              v-for="item in items"
              :key="item.id"
              class="rounded-2xl border border-slate-200 p-4"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="font-semibold text-slate-900">{{ item.name }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ item.email }}</div>
                <div class="mt-2 text-sm text-slate-500">
                  {{ roleLabel(item.role) }} · {{ item.active_vehicle_assignments_count ?? 0 }} aktivnih zaduženja
                </div>
                <div class="mt-2">
                  <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="statusBadgeClass(item.is_active)"
                  >
                    {{ item.is_active ? 'Aktivan' : 'Neaktivan' }}
                  </span>
                </div>
              </div>

              <div class="flex flex-col gap-2">
                <button
                    type="button"
                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    @click="router.push({ name: 'user-show', params: { id: item.id } })"
                >
                  Pregled
                </button>

                <button
                    v-if="canManage"
                    type="button"
                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    @click="router.push({ name: 'user-edit', params: { id: item.id } })"
                >
                  Uredi
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="text-sm text-slate-500">
            Stranica {{ page }} od {{ lastPage }}
          </div>

          <div class="flex gap-2">
            <button
                type="button"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="page <= 1"
                @click="usersStore.goToPage(page - 1)"
            >
              Prethodna
            </button>

            <button
                type="button"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="page >= lastPage"
                @click="usersStore.goToPage(page + 1)"
            >
              Sljedeća
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>