<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { AxiosError } from 'axios'
import api from '@/lib/axios'

type MetricItem = {
  key: string
  label: string
  value: number
}

type SummaryResponse = {
  metrics: MetricItem[]
  highlights: {
    top_mileage_vehicles: Array<{
      id: number
      name: string
      license_plate: string | null
      current_mileage: number | null
    }>
    upcoming_registrations: Array<{
      id: number
      name: string
      license_plate: string | null
      registration_expiry_date: string | null
    }>
    due_services: Array<{
      id: number
      service_type: string
      vehicle_name: string | null
      license_plate: string | null
      mileage_until_due: number | null
    }>
    top_assigned_users: Array<{
      id: number
      name: string
      email: string
      active_vehicle_assignments_count: number
    }>
  }
}

type ReportColumn = {
  key: string
  label: string
}

type ReportDataset = {
  title: string
  columns: ReportColumn[]
  rows: Record<string, string | number | null>[]
}

type ReportConfig = {
  label: string
  primaryLabel?: string
  primaryKey?: string
  primaryOptions?: Array<{ label: string; value: string }>
  secondaryLabel?: string
  secondaryKey?: string
  secondaryOptions?: Array<{ label: string; value: string }>
}

const summary = ref<SummaryResponse | null>(null)
const dataset = ref<ReportDataset | null>(null)
const loadingSummary = ref(false)
const loadingDataset = ref(false)
const exporting = ref(false)
const errorMessage = ref('')

const selectedReport = ref<'fleet' | 'registrations' | 'services' | 'assignments' | 'users' | 'alerts'>('fleet')

const filters = reactive({
  search: '',
  primary: '',
  secondary: '',
})

const reportConfigs: Record<string, ReportConfig> = {
  fleet: {
    label: 'Fleet',
    primaryLabel: 'Status',
    primaryKey: 'status',
    primaryOptions: [
      { label: 'Svi statusi', value: '' },
      { label: 'Active', value: 'active' },
      { label: 'Inactive', value: 'inactive' },
      { label: 'Maintenance', value: 'maintenance' },
    ],
    secondaryLabel: 'Zaduženje',
    secondaryKey: 'assignment',
    secondaryOptions: [
      { label: 'Sva vozila', value: '' },
      { label: 'Sa zaduženjem', value: 'assigned' },
      { label: 'Bez zaduženja', value: 'unassigned' },
    ],
  },
  registrations: {
    label: 'Registrations',
    primaryLabel: 'Stanje',
    primaryKey: 'status',
    primaryOptions: [
      { label: 'Sve', value: '' },
      { label: 'Expiring', value: 'expiring' },
      { label: 'Expired', value: 'expired' },
      { label: 'Valid', value: 'valid' },
    ],
    secondaryLabel: 'Prag dana',
    secondaryKey: 'days',
    secondaryOptions: [
      { label: '7 dana', value: '7' },
      { label: '14 dana', value: '14' },
      { label: '30 dana', value: '30' },
    ],
  },
  services: {
    label: 'Services',
    primaryLabel: 'Stanje',
    primaryKey: 'status',
    primaryOptions: [
      { label: 'Sve', value: '' },
      { label: 'Due', value: 'due' },
      { label: 'Due soon', value: 'due_soon' },
      { label: 'OK', value: 'ok' },
      { label: 'No target', value: 'no_target' },
    ],
  },
  assignments: {
    label: 'Assignments',
    primaryLabel: 'Status',
    primaryKey: 'status',
    primaryOptions: [
      { label: 'Svi statusi', value: '' },
      { label: 'Active', value: 'active' },
      { label: 'Ended', value: 'ended' },
      { label: 'Cancelled', value: 'cancelled' },
    ],
  },
  users: {
    label: 'Users',
    primaryLabel: 'Rola',
    primaryKey: 'role',
    primaryOptions: [
      { label: 'Sve role', value: '' },
      { label: 'Tenant admin', value: 'tenant_admin' },
      { label: 'Tenant user', value: 'tenant_user' },
    ],
    secondaryLabel: 'Status',
    secondaryKey: 'status',
    secondaryOptions: [
      { label: 'Svi statusi', value: '' },
      { label: 'Active', value: 'active' },
      { label: 'Inactive', value: 'inactive' },
    ],
  },
  alerts: {
    label: 'Alerts',
    primaryLabel: 'Status',
    primaryKey: 'status',
    primaryOptions: [
      { label: 'Sve', value: '' },
      { label: 'Unread', value: 'unread' },
      { label: 'Read', value: 'read' },
    ],
    secondaryLabel: 'Severity',
    secondaryKey: 'severity',
    secondaryOptions: [
      { label: 'Sve', value: '' },
      { label: 'Info', value: 'info' },
      { label: 'Low', value: 'low' },
      { label: 'Medium', value: 'medium' },
      { label: 'High', value: 'high' },
    ],
  },
}

const currentConfig = computed(() => reportConfigs[selectedReport.value])

onMounted(async () => {
  await fetchSummary()
  await fetchDataset()
})

watch(selectedReport, async () => {
  filters.search = ''
  filters.primary = ''
  filters.secondary = selectedReport.value === 'registrations' ? '7' : ''
  await fetchDataset()
})

function buildParams() {
  const params: Record<string, string> = {}

  if (filters.search.trim()) {
    params.search = filters.search.trim()
  }

  if (currentConfig.value.primaryKey && filters.primary) {
    params[currentConfig.value.primaryKey] = filters.primary
  }

  if (currentConfig.value.secondaryKey && filters.secondary) {
    params[currentConfig.value.secondaryKey] = filters.secondary
  }

  return params
}

async function fetchSummary() {
  loadingSummary.value = true

  try {
    const { data } = await api.get<SummaryResponse>('/api/reports/summary')
    summary.value = data
  } finally {
    loadingSummary.value = false
  }
}

async function fetchDataset() {
  loadingDataset.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get<ReportDataset>(`/api/reports/${selectedReport.value}`, {
      params: buildParams(),
    })

    dataset.value = data
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    errorMessage.value = axiosError.response?.data?.message ?? 'Izvještaj nije mogao biti učitan.'
  } finally {
    loadingDataset.value = false
  }
}

async function exportReport(format: 'excel' | 'pdf') {
  exporting.value = true

  try {
    const response = await api.get(`/api/reports/${selectedReport.value}/${format}`, {
      params: buildParams(),
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const contentDisposition = response.headers['content-disposition'] as string | undefined
    const filenameMatch = contentDisposition?.match(/filename="?([^"]+)"?/)
    const filename = filenameMatch?.[1] ?? `report.${format === 'excel' ? 'xlsx' : 'pdf'}`

    const url = window.URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    window.URL.revokeObjectURL(url)
  } finally {
    exporting.value = false
  }
}

function formatCell(value: unknown) {
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          Reports
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Pregled operativnih izvještaja uz Excel i PDF eksport.
        </p>
      </div>

      <div class="flex gap-3">
        <button
            type="button"
            class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-50"
            :disabled="exporting"
            @click="exportReport('excel')"
        >
          Export Excel
        </button>

        <button
            type="button"
            class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
            :disabled="exporting"
            @click="exportReport('pdf')"
        >
          Export PDF
        </button>
      </div>
    </div>

    <div v-if="loadingSummary" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm text-sm text-slate-500">
      Učitavanje summary podataka...
    </div>

    <template v-else-if="summary">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div
            v-for="metric in summary.metrics"
            :key="metric.key"
            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <div class="text-sm text-slate-500">{{ metric.label }}</div>
          <div class="mt-2 text-3xl font-bold tracking-tight text-slate-900">
            {{ metric.value }}
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-slate-900">Top kilometraža</h2>
          <div class="space-y-3">
            <div
                v-for="item in summary.highlights.top_mileage_vehicles"
                :key="item.id"
                class="rounded-2xl border border-slate-200 p-4"
            >
              <div class="font-semibold text-slate-900">{{ item.name }}</div>
              <div class="mt-1 text-sm text-slate-500">{{ item.license_plate || '—' }}</div>
              <div class="mt-2 text-sm font-medium text-slate-800">{{ item.current_mileage ?? 0 }} km</div>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-slate-900">Uskoro ističu registracije</h2>
          <div class="space-y-3">
            <div
                v-for="item in summary.highlights.upcoming_registrations"
                :key="item.id"
                class="rounded-2xl border border-slate-200 p-4"
            >
              <div class="font-semibold text-slate-900">{{ item.name }}</div>
              <div class="mt-1 text-sm text-slate-500">{{ item.license_plate || '—' }}</div>
              <div class="mt-2 text-sm font-medium text-slate-800">{{ item.registration_expiry_date || '—' }}</div>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-slate-900">Dospjeli servisi</h2>
          <div class="space-y-3">
            <div
                v-for="item in summary.highlights.due_services"
                :key="item.id"
                class="rounded-2xl border border-slate-200 p-4"
            >
              <div class="font-semibold text-slate-900">{{ item.service_type }}</div>
              <div class="mt-1 text-sm text-slate-500">
                {{ item.vehicle_name || '—' }} · {{ item.license_plate || '—' }}
              </div>
              <div class="mt-2 text-sm font-medium text-slate-800">
                {{ item.mileage_until_due ?? 0 }} km
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-slate-900">Najviše aktivnih zaduženja</h2>
          <div class="space-y-3">
            <div
                v-for="item in summary.highlights.top_assigned_users"
                :key="item.id"
                class="rounded-2xl border border-slate-200 p-4"
            >
              <div class="font-semibold text-slate-900">{{ item.name }}</div>
              <div class="mt-1 text-sm text-slate-500">{{ item.email }}</div>
              <div class="mt-2 text-sm font-medium text-slate-800">
                {{ item.active_vehicle_assignments_count }} aktivnih zaduženja
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex flex-wrap gap-2">
        <button
            v-for="(config, key) in reportConfigs"
            :key="key"
            type="button"
            class="rounded-2xl px-4 py-3 text-sm font-semibold transition"
            :class="selectedReport === key
            ? 'bg-slate-900 text-white'
            : 'border border-slate-300 text-slate-700 hover:bg-slate-100'"
            @click="selectedReport = key as typeof selectedReport"
        >
          {{ config.label }}
        </button>
      </div>

      <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_220px_220px_auto]">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Pretraga</label>
          <input
              v-model="filters.search"
              type="text"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              placeholder="Pretraga po trenutnom reportu"
              @keyup.enter="fetchDataset"
          />
        </div>

        <div v-if="currentConfig.primaryOptions?.length">
          <label class="mb-2 block text-sm font-medium text-slate-700">
            {{ currentConfig.primaryLabel }}
          </label>
          <select
              v-model="filters.primary"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
          >
            <option
                v-for="option in currentConfig.primaryOptions"
                :key="option.value"
                :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div v-if="currentConfig.secondaryOptions?.length">
          <label class="mb-2 block text-sm font-medium text-slate-700">
            {{ currentConfig.secondaryLabel }}
          </label>
          <select
              v-model="filters.secondary"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
          >
            <option
                v-for="option in currentConfig.secondaryOptions"
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
              @click="fetchDataset"
          >
            Primijeni filtere
          </button>
        </div>
      </div>

      <div
          v-if="errorMessage"
          class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ errorMessage }}
      </div>

      <div
          v-if="loadingDataset"
          class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        Učitavanje izvještaja...
      </div>

      <div
          v-else-if="dataset"
          class="mt-5 overflow-x-auto"
      >
        <div class="mb-4 text-lg font-semibold text-slate-900">
          {{ dataset.title }}
        </div>

        <table class="min-w-full text-left">
          <thead>
          <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
            <th
                v-for="column in dataset.columns"
                :key="column.key"
                class="pb-3 pr-4 font-medium"
            >
              {{ column.label }}
            </th>
          </tr>
          </thead>
          <tbody>
          <tr
              v-for="(row, index) in dataset.rows"
              :key="index"
              class="border-b border-slate-100 last:border-b-0"
          >
            <td
                v-for="column in dataset.columns"
                :key="column.key"
                class="py-4 pr-4 text-sm text-slate-700"
            >
              {{ formatCell(row[column.key]) }}
            </td>
          </tr>
          </tbody>
        </table>

        <div
            v-if="!dataset.rows.length"
            class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
        >
          Nema podataka za odabrane filtere.
        </div>
      </div>
    </div>
  </div>
</template>