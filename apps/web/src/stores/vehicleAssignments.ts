import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type VehicleAssignmentItem = {
    id: number
    tenant_id: number
    vehicle_id: number
    user_id: number
    assigned_by: number | null
    assignment_type: 'primary' | 'secondary' | 'temporary'
    assigned_from: string | null
    assigned_until: string | null
    unassigned_at: string | null
    status: 'active' | 'ended' | 'cancelled'
    notes: string | null
    start_mileage: number | null
    end_mileage: number | null
    user: {
        id: number
        name: string
        email: string
        role: string
        is_active: boolean
    } | null
    vehicle: {
        id: number
        name: string
        license_plate: string | null
        status: string
        current_mileage: number | null
    } | null
    assigned_by_user: {
        id: number
        name: string
    } | null
    created_at: string | null
    updated_at: string | null
}

type VehicleAssignmentsResponse = {
    current_page: number
    data: VehicleAssignmentItem[]
    last_page: number
    per_page: number
    total: number
}

type CreateAssignmentPayload = {
    vehicle_id: number
    user_id: number
    assignment_type: 'primary' | 'secondary' | 'temporary'
    assigned_from: string
    assigned_until: string | null
    notes: string | null
    start_mileage: number | null
}

type UpdateAssignmentPayload = {
    assignment_type: 'primary' | 'secondary' | 'temporary'
    assigned_from: string
    assigned_until: string | null
    notes: string | null
    start_mileage: number | null
    end_mileage: number | null
}

type EndAssignmentPayload = {
    unassigned_at: string | null
    end_mileage: number | null
    notes: string | null
}

export const useVehicleAssignmentsStore = defineStore('vehicleAssignments', () => {
    const items = ref<VehicleAssignmentItem[]>([])
    const currentAssignment = ref<VehicleAssignmentItem | null>(null)
    const loading = ref(false)
    const saving = ref(false)

    async function fetchAssignments(params?: {
        user_id?: number
        vehicle_id?: number
        status?: 'active' | 'ended' | 'cancelled'
        per_page?: number
        page?: number
    }): Promise<VehicleAssignmentsResponse> {
        loading.value = true

        try {
            const { data } = await api.get<VehicleAssignmentsResponse>('/api/vehicle-assignments', {
                params,
            })

            items.value = data.data
            return data
        } finally {
            loading.value = false
        }
    }

    async function fetchAssignment(id: number | string): Promise<VehicleAssignmentItem> {
        loading.value = true

        try {
            const { data } = await api.get<{ data: VehicleAssignmentItem }>(`/api/vehicle-assignments/${id}`)
            currentAssignment.value = data.data
            return data.data
        } finally {
            loading.value = false
        }
    }

    async function createAssignment(payload: CreateAssignmentPayload): Promise<VehicleAssignmentItem> {
        saving.value = true

        try {
            const { data } = await api.post<{ data: VehicleAssignmentItem }>('/api/vehicle-assignments', payload)
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function updateAssignment(id: number | string, payload: UpdateAssignmentPayload): Promise<VehicleAssignmentItem> {
        saving.value = true

        try {
            const { data } = await api.put<{ data: VehicleAssignmentItem }>(`/api/vehicle-assignments/${id}`, payload)
            currentAssignment.value = data.data
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function endAssignment(id: number | string, payload: EndAssignmentPayload): Promise<VehicleAssignmentItem> {
        saving.value = true

        try {
            const { data } = await api.post<{ data: VehicleAssignmentItem }>(`/api/vehicle-assignments/${id}/end`, payload)
            currentAssignment.value = data.data
            return data.data
        } finally {
            saving.value = false
        }
    }

    function clearCurrentAssignment() {
        currentAssignment.value = null
    }

    return {
        items,
        currentAssignment,
        loading,
        saving,
        fetchAssignments,
        fetchAssignment,
        createAssignment,
        updateAssignment,
        endAssignment,
        clearCurrentAssignment,
    }
})