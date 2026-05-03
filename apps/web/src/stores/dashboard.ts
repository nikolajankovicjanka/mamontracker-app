import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type DashboardOverview = {
    total_vehicles: number
    online_vehicles: number
    offline_vehicles: number
    expiring_registrations_count: number
    active_users_count: number
}

export type DashboardMapVehicle = {
    id: number
    name: string
    license_plate: string
    lat: number
    lng: number
    status: string
    online: boolean
    last_position_at: string | null
}

export type DashboardExpiringRegistration = {
    id: number
    name: string
    license_plate: string
    registration_expiry_date: string | null
    days_left: number | null
}

export type DashboardMileageVehicle = {
    id: number
    name: string
    license_plate: string
    current_mileage: number
    status: string
    online: boolean
}

export type DashboardActivityItem = {
    id: number
    title: string
    message: string
    severity: string | null
    sent_at: string | null
}

export type DashboardSummary = {
    generated_at: string
    overview: DashboardOverview
    map_vehicles: DashboardMapVehicle[]
    expiring_registrations: DashboardExpiringRegistration[]
    highest_mileage_vehicles: DashboardMileageVehicle[]
    recent_activity: DashboardActivityItem[]
}

export const useDashboardStore = defineStore('dashboard', () => {
    const summary = ref<DashboardSummary | null>(null)
    const loading = ref(false)

    async function fetchSummary(): Promise<void> {
        loading.value = true

        try {
            const { data } = await api.get<DashboardSummary>('/api/dashboard/summary')
            summary.value = data
        } finally {
            loading.value = false
        }
    }

    return {
        summary,
        loading,
        fetchSummary,
    }
})