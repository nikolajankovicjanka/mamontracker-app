import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type VehicleItem = {
    id: number
    name: string
    brand: string | null
    model: string | null
    production_year: number | null
    license_plate: string | null
    vin: string | null
    registration_expiry_date: string | null
    current_mileage: number
    status: 'active' | 'inactive' | 'maintenance'
    notes: string | null
    last_known_lat: number | null
    last_known_lng: number | null
    last_position_at: string | null
    last_speed_kph: number | null
    gps_device: {
        device_name: string
        model?: string | null
        provider?: string | null
        imei?: string | null
        traccar_device_id?: number | null
        is_active: boolean
        last_sync_at: string | null
    } | null
}

type VehiclesResponse = {
    current_page: number
    data: VehicleItem[]
    last_page: number
    per_page: number
    total: number
}

type VehiclePayload = {
    name: string
    brand: string
    model: string
    production_year: number | null
    license_plate: string
    vin: string | null
    registration_expiry_date: string | null
    current_mileage: number | null
    status: 'active' | 'inactive' | 'maintenance'
    notes: string | null
}

export const useVehiclesStore = defineStore('vehicles', () => {
    const items = ref<VehicleItem[]>([])
    const currentVehicle = ref<VehicleItem | null>(null)
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

    async function fetchVehicles(customPage?: number): Promise<void> {
        loading.value = true

        try {
            const targetPage = customPage ?? page.value

            const { data } = await api.get<VehiclesResponse>('/api/vehicles', {
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

    async function fetchVehicle(id: number | string): Promise<VehicleItem> {
        loading.value = true

        try {
            const { data } = await api.get<{ data: VehicleItem }>(`/api/vehicles/${id}`)
            currentVehicle.value = data.data
            return data.data
        } finally {
            loading.value = false
        }
    }

    async function createVehicle(payload: VehiclePayload): Promise<VehicleItem> {
        saving.value = true

        try {
            const { data } = await api.post<{ data: VehicleItem }>('/api/vehicles', payload)
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function updateVehicle(id: number | string, payload: VehiclePayload): Promise<VehicleItem> {
        saving.value = true

        try {
            const { data } = await api.put<{ data: VehicleItem }>(`/api/vehicles/${id}`, payload)
            currentVehicle.value = data.data
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function deleteVehicle(id: number | string): Promise<void> {
        deleting.value = true

        try {
            await api.delete(`/api/vehicles/${id}`)
        } finally {
            deleting.value = false
        }
    }

    async function applyFilters(): Promise<void> {
        page.value = 1
        await fetchVehicles(1)
    }

    async function goToPage(targetPage: number): Promise<void> {
        if (targetPage < 1 || targetPage > lastPage.value) {
            return
        }

        await fetchVehicles(targetPage)
    }

    function clearCurrentVehicle() {
        currentVehicle.value = null
    }

    return {
        items,
        currentVehicle,
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
        fetchVehicles,
        fetchVehicle,
        createVehicle,
        updateVehicle,
        deleteVehicle,
        applyFilters,
        goToPage,
        clearCurrentVehicle,
    }
})