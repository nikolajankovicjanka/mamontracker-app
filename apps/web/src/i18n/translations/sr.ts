export default {
    dashboard: {
        title: 'Pregled voznog parka',
        subtitle: 'Pregled u realnom vremenu za vozni park kompanije {company}.',
        lastUpdated: 'Posljednje ažuriranje:',
        loading: 'Učitavanje dashboarda...',

        cards: {
            fleet: 'Vozni park',
            total: 'ukupno',
            totalVehicles: 'Ukupno vozila',

            online: 'Online',
            onlineVehicles: 'Online vozila',

            offline: 'Offline',
            offlineVehicles: 'Offline vozila',

            registrations: 'Registracije',
            urgent: 'hitno',
            expiringSoon: 'Ističu uskoro',
        },

        map: {
            title: 'Mapa voznog parka uživo',
            live: 'Uživo',
            expand: 'Proširi mapu',
            collapse: 'Smanji',
            trackingMap: 'Fleet tracking mapa',
        },

        registrations: {
            title: 'Registracije koje ističu',
            shown: 'Prikazano:',
            empty: 'Nema registracija koje uskoro ističu.',
        },

        mileage: {
            title: 'Vozila sa najvećom kilometražom',
            top: 'Top {count}',
            vehicle: 'Vozilo',
            plates: 'Tablice',
            mileage: 'Kilometraža',
            status: 'Status',
        },

        activity: {
            title: 'Nedavne aktivnosti',
            subtitle: 'Posljednji događaji',
            empty: 'Još nema nedavnih aktivnosti.',
        },

        status: {
            online: 'Online',
            offline: 'Offline',
        },

        severity: {
            high: 'visok',
            medium: 'srednji',
            low: 'nizak',
            info: 'info',
        },

        daysLeft: {
            today: 'danas',
            one: '1 dan',
            many: '{count} dana',
        }
    },

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