import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
    history: createWebHistory(),
    routes: []
})

/**
 * Navigation guard
 */
router.beforeEach((to, from) => {
    // 

    return true
});

router.afterEach((to, from, failure) => {
    if (failure) {
        console.warn('Navigation error: ', failure)
    }
})

export default router
