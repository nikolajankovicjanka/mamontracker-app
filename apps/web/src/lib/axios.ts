import axios from 'axios'

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? 'http://mamont.localhost:8080',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
})

export default api