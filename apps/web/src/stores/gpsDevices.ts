import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type GpsDeviceItem = {
    id: number
    tenant_id: number
    vehicle_id: number | null
    provider: string
    device_name: string
    model: string | null
    imei: string
    traccar_device_id: number | null
    sim_number: string | null
    capabilities: Record<string, unknown> | null
    is_active: boolean
    last_payload: Record<string, unknown> | null
    last_sync_at: string | null
    created_at: string | null
    updated_at: string | null
    vehicle: {
        id: number
        name: string
        license_plate: string | null
    } | null
    assignment_history?: Array<{
        id: number
        assigned_at: string | null
        unassigned_at: string | null
        notes: string | null
        vehicle: {
            id: number
            name: string
            license_plate: string | null
        } | null
        assigned_by: {
            id: number
            name: string
        } | null
    }>
}

type GpsDevicesResponse = {
    current_page: number
    data: GpsDeviceItem[]
    last_page: number
    per_page: number
    total: number
}

type GpsDevicePayload = {
    vehicle_id: number | null
    provider: string
    device_name: string
    model: string | null
    imei: string
    traccar_device_id: number | null
    sim_number: string | null
    capabilities: Record<string, unknown> | null
    is_active: boolean
}

export const useGpsDevicesStore = defineStore('gpsDevices', () => {
    const items = ref<GpsDeviceItem[]>([])
    const currentDevice = ref<GpsDeviceItem | null>(null)
    const loading = ref(false)
    const saving = ref(false)
    const deleting = ref(false)

    const page = ref(1)
    const perPage = ref(10)
    const total = ref(0)
    const lastPage = ref(1)

    const search = ref('')
    const status = ref<string>('')

    const hasItems = computed(() => items.value.length > 0)

    async function fetchDevices(customPage?: number): Promise<void> {
        loading.value = true

        try {
            const targetPage = customPage ?? page.value

            const { data } = await api.get<GpsDevicesResponse>('/api/gps-devices', {
                params: {
                    page: targetPage,
                    per_page: perPage.value,
                    search: search.value || undefined,
                    status: status.value || undefined,
                },
            })

            items.value = data.data
            page.value = data.current_page
            perPage.value = data.per_page
            total.value = data.total
            lastPage.value = data.last_page
        } finally {
            loading.value = false
        }
    }

    async function fetchDevice(id: number | string): Promise<GpsDeviceItem> {
        loading.value = true

        try {
            const { data } = await api.get<{ data: GpsDeviceItem }>(`/api/gps-devices/${id}`)
            currentDevice.value = data.data
            return data.data
        } finally {
            loading.value = false
        }
    }

    async function createDevice(payload: GpsDevicePayload): Promise<GpsDeviceItem> {
        saving.value = true

        try {
            const { data } = await api.post<{ data: GpsDeviceItem }>('/api/gps-devices', payload)
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function updateDevice(id: number | string, payload: GpsDevicePayload): Promise<GpsDeviceItem> {
        saving.value = true

        try {
            const { data } = await api.put<{ data: GpsDeviceItem }>(`/api/gps-devices/${id}`, payload)
            currentDevice.value = data.data
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function deleteDevice(id: number | string): Promise<void> {
        deleting.value = true

        try {
            await api.delete(`/api/gps-devices/${id}`)
        } finally {
            deleting.value = false
        }
    }

    async function applyFilters(): Promise<void> {
        page.value = 1
        await fetchDevices(1)
    }

    async function goToPage(targetPage: number): Promise<void> {
        if (targetPage < 1 || targetPage > lastPage.value) {
            return
        }

        await fetchDevices(targetPage)
    }

    function clearCurrentDevice() {
        currentDevice.value = null
    }

    return {
        items,
        currentDevice,
        loading,
        saving,
        deleting,
        page,
        perPage,
        total,
        lastPage,
        search,
        status,
        hasItems,
        fetchDevices,
        fetchDevice,
        createDevice,
        updateDevice,
        deleteDevice,
        applyFilters,
        goToPage,
        clearCurrentDevice,
    }
})