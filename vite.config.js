import { defineConfig } from 'vite';
import { resolve } from 'path';
import vue from '@vitejs/plugin-vue';

// NOTE: Temporarily removed `laravel-vite-plugin` to avoid incompatible peer
// dependency issues when building with the current `vite` version on this
// environment. The app will still build frontend assets; if you rely on
// automatic Blade/Vite integration (hot refresh, blade directives), restore
// the plugin after installing a compatible version.

export default defineConfig({
    base: '/build/',
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js')
        }
    },
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        outDir: 'public/build',
        manifest: 'manifest.json',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                app: 'resources/js/app.js'
            }
        }
    }
});
