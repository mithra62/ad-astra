import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: 'eric.adadra.com',
        cors: {
            origin: [
                'http://127.0.0.1:8000',
                'http://localhost:8000',
            ],
        },
        hmr: {
            host: 'localhost',
            protocol: 'ws',
        },
    },
});
