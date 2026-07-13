import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // app.tsx = public SPA (bundles its own CSS via an import); app.css =
            // standalone entry for plain Blade pages outside the SPA (e.g. the rider
            // set-password page) that reference @vite('resources/css/app.css');
            // theme.css = custom Filament admin theme (loaded only in the panel via
            // ->viteTheme()).
            input: [
                'resources/js/app.tsx',
                'resources/css/app.css',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
