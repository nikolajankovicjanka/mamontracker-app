import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type RegistrationItem = {
    vehicle_id: number
    name: string
    brand: string | null
    model: string | null
    license_plate: string | null
    vin: string | null
    status: string
    current_mileage: number
    registration_expiry_date: string | null
    registration_status: 'valid' | 'expiring' | 'expired' | 'missing'
    days_left: number | null
}

type RegistrationsResponse = {
    current_page: number
    data: RegistrationItem[]
    last_page: number
    per_page: number
    total: number
}

type RegistrationPayload = {
    registration_expiry_date: string | null
}

export const useRegistrationsStore = defineStore('registrations', () => {
    const items = ref<RegistrationItem[]>([])
    const currentRegistration = ref<RegistrationItem | null>(null)
    const loading = ref(false)
    const saving = ref(false)

    const page = ref(1)
    const perPage = ref(10)
    const total = ref(0)
    const lastPage = ref(1)

    const search = ref('')
    const status = ref('')

    const hasItems = computed(() => items.value.length > 0)

    async function fetchRegistrations(customPage?: number): Promise<void> {
        loading.value = true

        try {
            const targetPage = customPage ?? page.value

            const { data } = await api.get<RegistrationsResponse>('/api/registrations', {
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

    async function fetchRegistration(vehicleId: number | string): Promise<RegistrationItem> {
        loading.value = true

        try {
            const { data } = await api.get<{ data: RegistrationItem }>(`/api/registrations/${vehicleId}`)
            currentRegistration.value = data.data
            return data.data
        } finally {
            loading.value = false
        }
    }

    async function updateRegistration(vehicleId: number | string, payload: RegistrationPayload): Promise<RegistrationItem> {
        saving.value = true

        try {
            const { data } = await api.put<{ data: RegistrationItem }>(`/api/registrations/${vehicleId}`, payload)
            currentRegistration.value = data.data
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function applyFilters(): Promise<void> {
        page.value = 1
        await fetchRegistrations(1)
    }

    async function goToPage(targetPage: number): Promise<void> {
        if (targetPage < 1 || targetPage > lastPage.value) {
            return
        }

        await fetchRegistrations(targetPage)
    }

    function clearCurrentRegistration() {
        currentRegistration.value = null
    }

    return {
        items,
        currentRegistration,
        loading,
        saving,
        page,
        perPage,
        total,
        lastPage,
        search,
        status,
        hasItems,
        fetchRegistrations,
        fetchRegistration,
        updateRegistration,
        applyFilters,
        goToPage,
        clearCurrentRegistration,
    }
})