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
        // Use esbuild for faster minification
        minify: 'esbuild',
        // Raise chunk size warning limit to 800KB
        chunkSizeWarningLimit: 800,
        // Enable CSS code splitting for faster page loads
        cssCodeSplit: true,
        rollupOptions: {
            input: {
                app: 'resources/js/app.js'
            },
            output: {
                // Manual chunk splitting: separates large vendor libs into cacheable chunks
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        // Core Vue / Inertia
                        if (id.includes('vue') || id.includes('@inertiajs') || id.includes('@vueuse')) {
                            return 'vendor-vue';
                        }
                        // Charts
                        if (id.includes('chart.js') || id.includes('vue-chartjs')) {
                            return 'vendor-charts';
                        }
                        // PDF / Canvas tools
                        if (id.includes('jspdf') || id.includes('html2canvas') || id.includes('html2pdf') || id.includes('pdfjs-dist')) {
                            return 'vendor-pdf';
                        }
                        // Excel / file utilities
                        if (id.includes('xlsx') || id.includes('file-saver') || id.includes('mammoth')) {
                            return 'vendor-files';
                        }
                        // Everything else from node_modules
                        return 'vendor';
                    }
                }
            }
        }
    }
});
