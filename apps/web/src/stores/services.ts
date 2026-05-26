import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type ServiceItem = {
    id: number
    tenant_id: number
    vehicle_id: number
    created_by: number | null
    service_type: string
    service_date: string | null
    mileage_at_service: number | null
    next_service_due_km: number | null
    notes: string | null
    service_status: 'ok' | 'due_soon' | 'due' | 'no_target'
    mileage_until_due: number | null
    vehicle: {
        id: number
        name: string
        license_plate: string | null
        current_mileage: number | null
    } | null
    creator: {
        id: number
        name: string
    } | null
    created_at: string | null
    updated_at: string | null
}

type ServicesResponse = {
    current_page: number
    data: ServiceItem[]
    last_page: number
    per_page: number
    total: number
}

type ServicePayload = {
    vehicle_id: number
    service_type: string
    service_date: string
    mileage_at_service: number
    next_service_due_km: number | null
    notes: string | null
}

export const useServicesStore = defineStore('services', () => {
    const items = ref<ServiceItem[]>([])
    const currentService = ref<ServiceItem | null>(null)
    const loading = ref(false)
    const saving = ref(false)
    const deleting = ref(false)

    const page = ref(1)
    const perPage = ref(10)
    const total = ref(0)
    const lastPage = ref(1)

    const search = ref('')
    const status = ref('')
    const vehicleId = ref<number | ''>('')

    const hasItems = computed(() => items.value.length > 0)

    async function fetchServices(customPage?: number): Promise<void> {
        loading.value = true

        try {
            const targetPage = customPage ?? page.value

            const { data } = await api.get<ServicesResponse>('/api/services', {
                params: {
                    page: targetPage,
                    per_page: perPage.value,
                    search: search.value || undefined,
                    status: status.value || undefined,
                    vehicle_id: vehicleId.value || undefined,
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

    async function fetchService(id: number | string): Promise<ServiceItem> {
        loading.value = true

        try {
            const { data } = await api.get<{ data: ServiceItem }>(`/api/services/${id}`)
            currentService.value = data.data
            return data.data
        } finally {
            loading.value = false
        }
    }

    async function createService(payload: ServicePayload): Promise<ServiceItem> {
        saving.value = true

        try {
            const { data } = await api.post<{ data: ServiceItem }>('/api/services', payload)
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function updateService(id: number | string, payload: ServicePayload): Promise<ServiceItem> {
        saving.value = true

        try {
            const { data } = await api.put<{ data: ServiceItem }>(`/api/services/${id}`, payload)
            currentService.value = data.data
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function deleteService(id: number | string): Promise<void> {
        deleting.value = true

        try {
            await api.delete(`/api/services/${id}`)
        } finally {
            deleting.value = false
        }
    }

    async function applyFilters(): Promise<void> {
        page.value = 1
        await fetchServices(1)
    }

    async function goToPage(targetPage: number): Promise<void> {
        if (targetPage < 1 || targetPage > lastPage.value) return
        await fetchServices(targetPage)
    }

    function clearCurrentService() {
        currentService.value = null
    }

    return {
        items,
        currentService,
        loading,
        saving,
        deleting,
        page,
        perPage,
        total,
        lastPage,
        search,
        status,
        vehicleId,
        hasItems,
        fetchServices,
        fetchService,
        createService,
        updateService,
        deleteService,
        applyFilters,
        goToPage,
        clearCurrentService,
    }
})