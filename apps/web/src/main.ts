import './assets/main.css'
import './echo'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { pinia } from './stores'
import i18n from './i18n'

createApp(App)
    .use(pinia)
    .use(router)
    .use(i18n)
    .mount('#app')