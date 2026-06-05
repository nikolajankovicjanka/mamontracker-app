export default {
    settings: {
        title: 'Einstellungen',
        subtitle: 'Mandanten-Einstellungen und Benutzereinstellungen.',
        loading: 'Einstellungen werden geladen...',

        sections: {
            tenant: 'Firmeneinstellungen',
            preferences: 'Benutzereinstellungen',
        },

        fields: {
            companyName: 'Firmenname',
            contactEmail: 'Kontakt-E-Mail',
            primaryColor: 'Primärfarbe',
            timezone: 'Zeitzone',
            currency: 'Währung',
            registrationWarningDays: 'Warnungstage für die Registrierung',
            serviceDueSoonKm: 'Schwellenwert für bald fälligen Service (km)',
            allowMultiplePrimaryAssignments: 'Mehrere aktive primäre Zuweisungen erlauben',
            language: 'Sprache',
            theme: 'Design',
        },

        theme: {
            light: 'Hell',
            dark: 'Dunkel',
            system: 'System',
        },

        actions: {
            saving: 'Speichern...',
            saveTenant: 'Firmeneinstellungen speichern',
            savePreferences: 'Einstellungen speichern',
        },

        messages: {
            tenantSaved: 'Firmeneinstellungen wurden erfolgreich gespeichert.',
            preferencesSaved: 'Benutzereinstellungen wurden erfolgreich gespeichert.',
            saveError: 'Einstellungen konnten nicht gespeichert werden.',
        },
    },
}