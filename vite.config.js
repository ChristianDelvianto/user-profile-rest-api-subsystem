import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import path from 'path'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss({
            optimize: true
        }),
        vue(),
        vueDevTools()
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './vue')
        },
        extensions: ['.js', '.json', '.ts', '.vue']
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**']
        },
    },
});
