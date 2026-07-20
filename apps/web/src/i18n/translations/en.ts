export default {
    dashboard: {
        title: 'Fleet Overview',
        subtitle: 'Real-time overview of company {company} fleet.',
        lastUpdated: 'Last updated:',
        loading: 'Loading dashboard...',

        cards: {
            fleet: 'Fleet',
            total: 'total',
            totalVehicles: 'Total vehicles',

            online: 'Online',
            onlineVehicles: 'Online vehicles',

            offline: 'Offline',
            offlineVehicles: 'Offline vehicles',

            registrations: 'Registrations',
            urgent: 'urgent',
            expiringSoon: 'Expiring soon',
        },

        map: {
            title: 'Live Fleet Map',
            live: 'Live',
            expand: 'Expand map',
            collapse: 'Collapse',
            trackingMap: 'Fleet tracking map',
        },

        registrations: {
            title: 'Expiring Registrations',
            shown: 'Shown:',
            empty: 'No registrations expiring soon.',
        },

        mileage: {
            title: 'Vehicles with highest mileage',
            top: 'Top {count}',
            vehicle: 'Vehicle',
            plates: 'License plates',
            mileage: 'Mileage',
            status: 'Status',
        },

        activity: {
            title: 'Recent Activity',
            subtitle: 'Latest events',
            empty: 'No recent activity yet.',
        },

        status: {
            online: 'Online',
            offline: 'Offline',
        },

        severity: {
            high: 'high',
            medium: 'medium',
            low: 'low',
            info: 'info',
        },

        daysLeft: {
            today: 'today',
            one: '1 day',
            many: '{count} days',
        }
    },

    settings: {
        title: 'Settings',
        subtitle: 'Company settings and user preferences.',
        loading: 'Loading settings...',

        sections: {
            tenant: 'Company Settings',
            preferences: 'User Preferences',
        },

        fields: {
            companyName: 'Company name',
            contactEmail: 'Contact email',
            primaryColor: 'Primary color',
            timezone: 'Timezone',
            currency: 'Currency',
            registrationWarningDays: 'Registration warning days',
            serviceDueSoonKm: 'Service due soon threshold (km)',
            allowMultiplePrimaryAssignments: 'Allow multiple active primary assignments',
            language: 'Language',
            theme: 'Theme',
        },

        theme: {
            light: 'Light',
            dark: 'Dark',
            system: 'System',
        },

        actions: {
            saving: 'Saving...',
            saveTenant: 'Save company settings',
            savePreferences: 'Save preferences',
        },

        messages: {
            tenantSaved: 'Company settings saved successfully.',
            preferencesSaved: 'Preferences saved successfully.',
            saveError: 'Unable to save settings.',
        },
    },
}