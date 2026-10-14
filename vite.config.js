import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/sass/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5 still uses @import and Sass's global functions.
                quietDeps: true,
                silenceDeprecations: ['import'],
            },
        },
    },
    server: {
        // An IPv4 address, because CSP can't allow an IPv6 one like [::1]
        // (see app/Http/Middleware/SecurityHeaders.php).
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
