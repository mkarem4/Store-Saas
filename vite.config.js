import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/filament/panel.js', 'resources/css/filament/yulio/theme.css', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            // Self-hosted at build time; same families as the Filament panels.
            fonts: [
                bunny('Geist', {
                    weights: [400, 500, 600],
                    subsets: ['latin'],
                    preload: [{ weight: 400 }, { weight: 600 }],
                }),
                bunny('Readex Pro', {
                    weights: [400, 500, 600],
                    subsets: ['arabic', 'latin'],
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
