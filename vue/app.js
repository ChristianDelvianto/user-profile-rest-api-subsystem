import { createApp } from 'vue'
import Api from './services/index.js'
import App from './App.vue'

const API_VERSION = import.meta.env.VITE_API_VERSION || 'v1'

Api.defaults.baseURL = `/api/${API_VERSION}`

createApp(App).mount('#app')
