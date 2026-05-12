<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useRegistrationsStore } from '@/stores/registrations'

const router = useRouter()
const auth = useAuthStore()
const registrationsStore = useRegistrationsStore()

const {
  items,
  loading,
  page,
  lastPage,
  total,
  search,
  status,
  hasItems,
} = storeToRefs(registrationsStore)

const canManage = computed(() => auth.user?.role === 'tenant_admin')

onMounted(async () => {
  await registrationsStore.fetchRegistrations()
})

const statusOptions = [
  { label: 'Svi statusi', value: '' },
  { label: 'Važeća', value: 'valid' },
  { label: 'Ističe uskoro', value: 'expiring' },
  { label: 'Istekla', value: 'expired' },
  { label: 'Nije postavljena', value: 'missing' },
]

const from = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * registrationsStore.perPage + 1
})

const to = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * registrationsStore.perPage + items.value.length
})

function formatDate(date: string | null) {
  if (!date) return '—'

  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(date))
}

function daysLeftLabel(daysLeft: number | null) {
  if (daysLeft === null) return '—'
  if (daysLeft < 0) return `${Math.abs(daysLeft)} dana prekoračeno`
  if (daysLeft === 0) return 'Ističe danas'
  if (daysLeft === 1) return '1 dan'
  return `${daysLeft} dana`
}

function badgeClass(value: string) {
  if (value === 'valid') return 'bg-emerald-50 text-emerald-700'
  if (value === 'expiring') return 'bg-amber-50 text-amber-700'
  if (value === 'expired') return 'bg-rose-50 text-rose-700'
  return 'bg-slate-100 text-slate-700'
}

function badgeLabel(value: string) {
  if (value === 'valid') return 'Važeća'
  if (value === 'expiring') return 'Ističe uskoro'
  if (value === 'expired') return 'Istekla'
  return 'Nedostaje'
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          Registracije
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Pregled i upravljanje datumima isteka registracije po vozilu.
        </p>
      </div>

      <div class="text-sm text-slate-500">
        Ukupno zapisa:
        <span class="font-semibold text-slate-800">{{ total }}</span>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_220px_auto]">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Pretraga</label>
          <input
              v-model="search"
              type="text"
              placeholder="Naziv vozila, marka, model, tablice ili VIN"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @keyup.enter="registrationsStore.applyFilters()"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
          <select
              v-model="status"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @change="registrationsStore.applyFilters()"
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
              @click="registrationsStore.applyFilters()"
          >
            Primijeni filtere
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Lista registracija</h2>
        <div class="text-sm text-slate-500">
          Prikazano {{ from }}–{{ to }} od {{ total }}
        </div>
      </div>

      <div
          v-if="loading"
          class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        Učitavanje registracija...
      </div>

      <div
          v-else-if="!hasItems"
          class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        Nema zapisa za odabrane filtere.
      </div>

      <template v-else>
        <div class="hidden overflow-x-auto xl:block">
          <table class="min-w-full text-left">
            <thead>
            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <th class="pb-3 font-medium">Vozilo</th>
              <th class="pb-3 font-medium">Tablice</th>
              <th class="pb-3 font-medium">Datum isteka</th>
              <th class="pb-3 font-medium">Preostalo</th>
              <th class="pb-3 font-medium">Status</th>
              <th class="pb-3 font-medium">Akcija</th>
            </tr>
            </thead>
            <tbody>
            <tr
                v-for="item in items"
                :key="item.vehicle_id"
                class="border-b border-slate-100 last:border-b-0"
            >
              <td class="py-4">
                <div class="font-semibold text-slate-900">{{ item.name }}</div>
                <div class="mt-1 text-sm text-slate-500">
                  {{ item.brand }} {{ item.model }}
                </div>
              </td>

              <td class="py-4 text-slate-600">
                {{ item.license_plate || '—' }}
              </td>

              <td class="py-4 text-slate-600">
                {{ formatDate(item.registration_expiry_date) }}
              </td>

              <td class="py-4 font-medium text-slate-800">
                {{ daysLeftLabel(item.days_left) }}
              </td>

              <td class="py-4">
                  <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="badgeClass(item.registration_status)"
                  >
                    {{ badgeLabel(item.registration_status) }}
                  </span>
              </td>

              <td class="py-4">
                <button
                    v-if="canManage"
                    type="button"
                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    @click="router.push({ name: 'registration-edit', params: { vehicleId: item.vehicle_id } })"
                >
                  Uredi
                </button>
              </td>
            </tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-3 xl:hidden">
          <div
              v-for="item in items"
              :key="item.vehicle_id"
              class="rounded-2xl border border-slate-200 p-4"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="font-semibold text-slate-900">{{ item.name }}</div>
                <div class="mt-1 text-sm text-slate-500">
                  {{ item.license_plate || '—' }}
                </div>
                <div class="mt-2 text-sm text-slate-500">
                  Istek: {{ formatDate(item.registration_expiry_date) }}
                </div>
                <div class="mt-1 text-sm font-medium text-slate-800">
                  {{ daysLeftLabel(item.days_left) }}
                </div>
              </div>

              <div class="flex flex-col items-end gap-2">
                <span
                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="badgeClass(item.registration_status)"
                >
                  {{ badgeLabel(item.registration_status) }}
                </span>

                <button
                    v-if="canManage"
                    type="button"
                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    @click="router.push({ name: 'registration-edit', params: { vehicleId: item.vehicle_id } })"
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
                @click="registrationsStore.goToPage(page - 1)"
            >
              Prethodna
            </button>

            <button
                type="button"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="page >= lastPage"
                @click="registrationsStore.goToPage(page + 1)"
            >
              Sljedeća
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>