import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({

    server: {
        host: "https://internal-antelope-saved.ngrok-free.app"
    },


    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
