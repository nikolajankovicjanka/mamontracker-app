export default {
    settings: {
        title: 'Settings',
        subtitle: 'Tenant settings and user preferences.',
        loading: 'Loading settings...',

        sections: {
            tenant: 'Tenant settings',
            preferences: 'User preferences',
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
            saveTenant: 'Save tenant settings',
            savePreferences: 'Save preferences',
        },

        messages: {
            tenantSaved: 'Tenant settings saved successfully.',
            preferencesSaved: 'Preferences saved successfully.',
            saveError: 'Settings could not be saved.',
        },
    },
}