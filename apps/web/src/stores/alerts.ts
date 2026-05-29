import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type AlertItem = {
    id: number
    type: string
    severity: 'info' | 'low' | 'medium' | 'high'
    title: string
    message: string
    is_read: boolean
    read_at: string | null
    seen_at: string | null
    sent_at: string | null
    vehicle: {
        id: number
        name: string
        license_plate: string | null
    } | null
    gps_device: {
        id: number
        device_name: string
    } | null
    route_name: string | null
    route_params: Record<string, string | number>
    meta: Record<string, unknown>
}

type AlertsResponse = {
    current_page: number
    data: AlertItem[]
    last_page: number
    per_page: number
    total: number
    unread_count: number
}

export const useAlertsStore = defineStore('alerts', () => {
    const items = ref<AlertItem[]>([])
    const unreadCount = ref(0)
    const loading = ref(false)
    const markingAllRead = ref(false)

    async function fetchAlerts(filter: 'all' | 'unread' = 'all', perPage = 8): Promise<void> {
        loading.value = true

        try {
            const { data } = await api.get<AlertsResponse>('/api/alerts', {
                params: {
                    filter,
                    per_page: perPage,
                },
            })

            items.value = data.data
            unreadCount.value = data.unread_count
        } finally {
            loading.value = false
        }
    }

    async function fetchUnreadCount(): Promise<void> {
        const { data } = await api.get<{ unread_count: number }>('/api/alerts/unread-count')
        unreadCount.value = data.unread_count
    }

    async function markAsRead(id: number): Promise<void> {
        await api.post(`/api/alerts/${id}/read`)

        const target = items.value.find((item) => item.id === id)

        if (target && !target.is_read) {
            target.is_read = true
            target.read_at = new Date().toISOString()
        }

        unreadCount.value = Math.max(0, unreadCount.value - 1)
    }

    async function markAllAsRead(): Promise<void> {
        markingAllRead.value = true

        try {
            await api.post('/api/alerts/read-all')

            const now = new Date().toISOString()

            items.value = items.value.map((item) => ({
                ...item,
                is_read: true,
                read_at: item.read_at ?? now,
            }))

            unreadCount.value = 0
        } finally {
            markingAllRead.value = false
        }
    }

    function clearAlerts() {
        items.value = []
        unreadCount.value = 0
    }

    return {
        items,
        unreadCount,
        loading,
        markingAllRead,
        fetchAlerts,
        fetchUnreadCount,
        markAsRead,
        markAllAsRead,
        clearAlerts,
    }
})