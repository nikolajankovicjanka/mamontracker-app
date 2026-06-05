import { createI18n } from 'vue-i18n'
import en from './translations/en'
import sr from './translations/sr'
import de from './translations/de'

const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: {
        en,
        sr,
        de,
    },
})

export default i18n