<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useServicesStore } from '@/stores/services'

const router = useRouter()
const auth = useAuthStore()
const servicesStore = useServicesStore()

const {
  items,
  loading,
  page,
  lastPage,
  total,
  search,
  status,
  hasItems,
} = storeToRefs(servicesStore)

const canManage = computed(() => auth.user?.role === 'tenant_admin')

onMounted(async () => {
  await servicesStore.fetchServices()
})

const statusOptions = [
  { label: 'Svi statusi', value: '' },
  { label: 'U redu', value: 'ok' },
  { label: 'Uskoro dospijeva', value: 'due_soon' },
  { label: 'Dospio', value: 'due' },
  { label: 'Nema limita', value: 'no_target' },
]

const from = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * servicesStore.perPage + 1
})

const to = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * servicesStore.perPage + items.value.length
})

function formatDate(date: string | null) {
  if (!date) return '—'

  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(date))
}

function formatMileage(value: number | null) {
  if (value === null) return '—'
  return `${new Intl.NumberFormat('sr-Latn-RS').format(value)} km`
}

function dueLabel(value: number | null) {
  if (value === null) return '—'
  if (value < 0) return `${Math.abs(value)} km prekoračeno`
  if (value === 0) return 'Dospijeva sada'
  return `${new Intl.NumberFormat('sr-Latn-RS').format(value)} km`
}

function statusBadgeClass(value: string) {
  if (value === 'ok') return 'bg-emerald-50 text-emerald-700'
  if (value === 'due_soon') return 'bg-amber-50 text-amber-700'
  if (value === 'due') return 'bg-rose-50 text-rose-700'
  return 'bg-slate-100 text-slate-700'
}

function statusLabel(value: string) {
  if (value === 'ok') return 'U redu'
  if (value === 'due_soon') return 'Uskoro dospijeva'
  if (value === 'due') return 'Dospio'
  return 'Nema limita'
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          Servisi
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Upravljanje servisnim zapisima po vozilu.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div class="text-sm text-slate-500">
          Ukupno servisa:
          <span class="font-semibold text-slate-800">{{ total }}</span>
        </div>

        <button
            v-if="canManage"
            type="button"
            class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800"
            @click="router.push({ name: 'service-create' })"
        >
          Dodaj servis
        </button>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_220px_auto]">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Pretraga</label>
          <input
              v-model="search"
              type="text"
              placeholder="Tip servisa, vozilo ili tablice"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @keyup.enter="servicesStore.applyFilters()"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
          <select
              v-model="status"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @change="servicesStore.applyFilters()"
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
              @click="servicesStore.applyFilters()"
          >
            Primijeni filtere
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Lista servisa</h2>
        <div class="text-sm text-slate-500">
          Prikazano {{ from }}–{{ to }} od {{ total }}
        </div>
      </div>

      <div
          v-if="loading"
          class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        Učitavanje servisa...
      </div>

      <div
          v-else-if="!hasItems"
          class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        Nema servisa za odabrane filtere.
      </div>

      <template v-else>
        <div class="hidden overflow-x-auto xl:block">
          <table class="min-w-full text-left">
            <thead>
            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <th class="pb-3 font-medium">Vozilo</th>
              <th class="pb-3 font-medium">Tip servisa</th>
              <th class="pb-3 font-medium">Datum servisa</th>
              <th class="pb-3 font-medium">Sljedeći limit</th>
              <th class="pb-3 font-medium">Preostalo</th>
              <th class="pb-3 font-medium">Status</th>
            </tr>
            </thead>
            <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
                class="border-b border-slate-100 last:border-b-0"
            >
              <td class="py-4">
                <button
                    type="button"
                    class="text-left"
                    @click="router.push({ name: 'service-show', params: { id: item.id } })"
                >
                  <div class="font-semibold text-slate-900 hover:underline">
                    {{ item.vehicle?.name || '—' }}
                  </div>
                  <div class="mt-1 text-sm text-slate-500">
                    {{ item.vehicle?.license_plate || '—' }}
                  </div>
                </button>
              </td>

              <td class="py-4 text-slate-700">
                {{ item.service_type }}
              </td>

              <td class="py-4 text-slate-600">
                {{ formatDate(item.service_date) }}
              </td>

              <td class="py-4 text-slate-600">
                {{ formatMileage(item.next_service_due_km) }}
              </td>

              <td class="py-4 font-medium text-slate-800">
                {{ dueLabel(item.mileage_until_due) }}
              </td>

              <td class="py-4">
                  <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="statusBadgeClass(item.service_status)"
                  >
                    {{ statusLabel(item.service_status) }}
                  </span>
              </td>
            </tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-3 xl:hidden">
          <div
              v-for="item in items"
              :key="item.id"
              class="cursor-pointer rounded-2xl border border-slate-200 p-4 hover:bg-slate-50"
              @click="router.push({ name: 'service-show', params: { id: item.id } })"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="font-semibold text-slate-900">
                  {{ item.vehicle?.name || '—' }}
                </div>
                <div class="mt-1 text-sm text-slate-500">
                  {{ item.service_type }}
                </div>
                <div class="mt-1 text-sm text-slate-500">
                  Datum: {{ formatDate(item.service_date) }}
                </div>
                <div class="mt-1 text-sm font-medium text-slate-800">
                  {{ dueLabel(item.mileage_until_due) }}
                </div>
              </div>

              <span
                  class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="statusBadgeClass(item.service_status)"
              >
                {{ statusLabel(item.service_status) }}
              </span>
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
                @click="servicesStore.goToPage(page - 1)"
            >
              Prethodna
            </button>

            <button
                type="button"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="page >= lastPage"
                @click="servicesStore.goToPage(page + 1)"
            >
              Sljedeća
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>