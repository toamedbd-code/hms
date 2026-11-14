import { defineConfig } from 'vite';
import { resolve } from 'path';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    base: '/build/',
    // Laravel serves static assets from public directly; do not copy public
    // into the nested public/build output directory.
    publicDir: false,

    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, 'resources/js'),
        },
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

        // Target modern browsers — smaller, faster output
        target: 'es2020',

        // esbuild is faster than Rollup's default terser
        minify: 'esbuild',

        // Use esbuild for CSS minification (avoids LightningCSS attribute-selector issues)
        cssMinify: 'esbuild',

        // CSS code splitting: only load CSS needed per page
        cssCodeSplit: true,

        // Skip printing per-chunk gzip sizes — saves 10–15 s on large builds
        reportCompressedSize: false,

        chunkSizeWarningLimit: 1000,

        esbuild: {
            target: 'es2020',
            // Strip all JSDoc / license comment blocks from output
            legalComments: 'none',
        },

        rollupOptions: {
            input: {
                app: 'resources/js/app.js',
            },
            output: {
                // Keep hashed file names for long-term browser caching and let Vite
                // keep lazy page dependencies out of the initial application bundle.
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]',
            },
        },
    },

    // Dev server tweaks
    server: {
        hmr: {
            overlay: true,
        },
    },
});
