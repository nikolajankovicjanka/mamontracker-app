export default {
    settings: {
        title: 'Podešavanja',
        subtitle: 'Podešavanja firme i korisničke preference.',
        loading: 'Učitavanje podešavanja...',

        sections: {
            tenant: 'Podešavanja firme',
            preferences: 'Korisničke preference',
        },

        fields: {
            companyName: 'Naziv firme',
            contactEmail: 'Kontakt email',
            primaryColor: 'Primarna boja',
            timezone: 'Vremenska zona',
            currency: 'Valuta',
            registrationWarningDays: 'Broj dana upozorenja za registraciju',
            serviceDueSoonKm: 'Prag za servis uskoro (km)',
            allowMultiplePrimaryAssignments: 'Dozvoli više aktivnih primarnih zaduženja',
            language: 'Jezik',
            theme: 'Tema',
        },

        theme: {
            light: 'Svijetla',
            dark: 'Tamna',
            system: 'Sistemska',
        },

        actions: {
            saving: 'Čuvanje...',
            saveTenant: 'Sačuvaj podešavanja firme',
            savePreferences: 'Sačuvaj preference',
        },

        messages: {
            tenantSaved: 'Podešavanja firme su uspješno sačuvana.',
            preferencesSaved: 'Preference su uspješno sačuvane.',
            saveError: 'Podešavanja nije moguće sačuvati.',
        },
    },
}