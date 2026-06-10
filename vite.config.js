import inertia from '@inertiajs/vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        inertia(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: [
                '**/storage/framework/views/**',
                '**/.cursor/**',
                '**/.planning/**',
            ],
        },
    },
});
