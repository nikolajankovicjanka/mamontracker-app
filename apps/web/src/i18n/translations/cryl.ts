export default {
    dashboard: {
        title: 'Преглед возног парка',
        subtitle: 'Преглед возног парка компаније {company} у реалном времену.',
        lastUpdated: 'Последње ажурирање:',
        loading: 'Учитавање dashboard-а...',

        cards: {
            fleet: 'Возни парк',
            total: 'укупно',
            totalVehicles: 'Укупно возила',

            online: 'Онлајн',
            onlineVehicles: 'Онлајн возила',

            offline: 'Офлајн',
            offlineVehicles: 'Офлајн возила',

            registrations: 'Регистрације',
            urgent: 'хитно',
            expiringSoon: 'Истичу ускоро',
        },

        map: {
            title: 'Мапа возног парка уживо',
            live: 'Уживо',
            expand: 'Прошири мапу',
            collapse: 'Смањи',
            trackingMap: 'Мапа праћења возног парка',
        },

        registrations: {
            title: 'Регистрације које истичу',
            shown: 'Приказано:',
            empty: 'Нема регистрација које ускоро истичу.',
        },

        mileage: {
            title: 'Возила са највећом километражом',
            top: 'Топ {count}',
            vehicle: 'Возило',
            plates: 'Регистарске таблице',
            mileage: 'Километража',
            status: 'Статус',
        },

        activity: {
            title: 'Недавне активности',
            subtitle: 'Последњи догађаји',
            empty: 'Још нема недавних активности.',
        },

        status: {
            online: 'Онлајн',
            offline: 'Офлајн',
        },

        severity: {
            high: 'висок',
            medium: 'средњи',
            low: 'низак',
            info: 'инфо',
        },

        daysLeft: {
            today: 'данас',
            one: '1 дан',
            many: '{count} дана',
        }
    },

    settings: {
        title: 'Подешавања',
        subtitle: 'Подешавања фирме и корисничке поставке.',
        loading: 'Учитавање подешавања...',

        sections: {
            tenant: 'Подешавања фирме',
            preferences: 'Корисничке поставке',
        },

        fields: {
            companyName: 'Назив фирме',
            contactEmail: 'Контакт email',
            primaryColor: 'Примарна боја',
            timezone: 'Временска зона',
            currency: 'Валута',
            registrationWarningDays: 'Број дана упозорења за регистрацију',
            serviceDueSoonKm: 'Праг за сервис ускоро (km)',
            allowMultiplePrimaryAssignments: 'Дозволи више активних примарних задужења',
            language: 'Језик',
            theme: 'Тема',
        },

        theme: {
            light: 'Светла',
            dark: 'Тамна',
            system: 'Системска',
        },

        actions: {
            saving: 'Чување...',
            saveTenant: 'Сачувај подешавања фирме',
            savePreferences: 'Сачувај поставке',
        },

        messages: {
            tenantSaved: 'Подешавања фирме су успешно сачувана.',
            preferencesSaved: 'Поставке су успешно сачуване.',
            saveError: 'Није могуће сачувати подешавања.',
        },
    },
}