import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAuthStore = defineStore('auth', () => {
    const isAuthenticated = ref(false)
    const isLoggedOut = ref(false)
    
    return {
        isAuthenticated,
        isLoggedOut
    }
})
