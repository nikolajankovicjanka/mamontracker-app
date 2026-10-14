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
    last_speed_kph?: number | null
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

export type VehicleTelemetryEvent = {
    vehicle: {
        id: number
        name: string
        license_plate: string
        online: boolean
        lat: number | null
        lng: number | null
        last_position_at: string | null
        last_speed_kph: number | null
        current_mileage: number | null
    }
    telemetry: Record<string, unknown>
}

type TelemetryVehicle = VehicleTelemetryEvent['vehicle']

function timestamp(value: string | null): number {
    const parsed = value ? Date.parse(value) : NaN
    return Number.isFinite(parsed) ? parsed : 0
}

function validPosition(vehicle: TelemetryVehicle): boolean {
    return typeof vehicle.lat === 'number'
        && typeof vehicle.lng === 'number'
        && Number.isFinite(vehicle.lat)
        && Number.isFinite(vehicle.lng)
        && Math.abs(vehicle.lat) <= 90
        && Math.abs(vehicle.lng) <= 180
}

export const useDashboardStore = defineStore('dashboard', () => {
    const summary = ref<DashboardSummary | null>(null)
    const loading = ref(false)

    // Odgovori prethodnog tenanta ili sesije ne smiju mijenjati dashboard.
    let epoch = 0
    let revision = 0
    let inFlight: Promise<void> | null = null

    const latestTelemetry = new Map<number, {
        vehicle: TelemetryVehicle
        revision: number
    }>()

    function reset(): void {
        epoch += 1
        revision = 0
        summary.value = null
        loading.value = false
        inFlight = null
        latestTelemetry.clear()
    }

    function patchVehicle(
        target: DashboardSummary,
        vehicle: TelemetryVehicle,
    ): void {
        const mapVehicle = target.map_vehicles.find(
            (item) => item.id === vehicle.id,
        )

        if (mapVehicle) {
            mapVehicle.name = vehicle.name
            mapVehicle.license_plate = vehicle.license_plate
            mapVehicle.online = vehicle.online

            // Poziciju mijenjamo samo ako nije starija od postojeće.
            if (
                timestamp(vehicle.last_position_at)
                >= timestamp(mapVehicle.last_position_at)
            ) {
                if (validPosition(vehicle)) {
                    mapVehicle.lat = vehicle.lat!
                    mapVehicle.lng = vehicle.lng!
                    mapVehicle.last_position_at = vehicle.last_position_at
                    mapVehicle.last_speed_kph = vehicle.last_speed_kph
                }
            }
        }

        const mileageVehicle = target.highest_mileage_vehicles.find(
            (item) => item.id === vehicle.id,
        )

        if (mileageVehicle) {
            mileageVehicle.name = vehicle.name
            mileageVehicle.license_plate = vehicle.license_plate
            mileageVehicle.online = vehicle.online

            if (
                typeof vehicle.current_mileage === 'number'
                && Number.isFinite(vehicle.current_mileage)
                && vehicle.current_mileage >= 0
            ) {
                mileageVehicle.current_mileage = vehicle.current_mileage
            }
        }
    }

    function applyTelemetry(event: VehicleTelemetryEvent): void {
        const vehicle = event.vehicle
        const previous = latestTelemetry.get(vehicle.id)

        const current = summary.value?.map_vehicles.find(
            (item) => item.id === vehicle.id,
        )

        const knownTime = Math.max(
            timestamp(previous?.vehicle.last_position_at ?? null),
            timestamp(current?.last_position_at ?? null),
        )

        // Eventi iz queue-a mogu stići pogrešnim redoslijedom.
        if (timestamp(vehicle.last_position_at) < knownTime) {
            return
        }

        revision += 1

        latestTelemetry.set(vehicle.id, {
            vehicle: { ...vehicle },
            revision,
        })

        if (summary.value) {
            patchVehicle(summary.value, vehicle)
        }
    }

    function requestSummary(): Promise<void> {
        // Više istovremenih osvježavanja koristi isti HTTP zahtjev.
        if (inFlight) {
            return inFlight
        }

        const requestEpoch = epoch
        const requestRevision = revision

        const request = api
            .get<DashboardSummary>('/api/dashboard/summary')
            .then(({ data }) => {
                if (requestEpoch !== epoch) {
                    return
                }

                for (const [id, update] of latestTelemetry) {
                    const snapshotVehicle = data.map_vehicles.find(
                        (item) => item.id === id,
                    )

                    const arrivedDuringRequest =
                        update.revision > requestRevision

                    const snapshotIsOlder =
                        snapshotVehicle
                        && timestamp(snapshotVehicle.last_position_at)
                        < timestamp(update.vehicle.last_position_at)

                    // HTTP odgovor ne smije prepisati noviju socket poziciju.
                    if (arrivedDuringRequest || snapshotIsOlder) {
                        patchVehicle(data, update.vehicle)
                    } else {
                        latestTelemetry.delete(id)
                    }
                }

                summary.value = data
            })
            .finally(() => {
                if (inFlight === request) {
                    inFlight = null
                }
            })

        inFlight = request
        return request
    }

    async function fetchSummary(): Promise<void> {
        const requestEpoch = epoch
        loading.value = true

        try {
            await requestSummary()
        } finally {
            if (requestEpoch === epoch) {
                loading.value = false
            }
        }
    }

    async function refreshSummary(): Promise<void> {
        try {
            await requestSummary()
        } catch (error) {
            console.error('Dashboard refresh failed:', error)
        }
    }

    return {
        summary,
        loading,
        fetchSummary,
        refreshSummary,
        applyTelemetry,
        reset,
    }
})