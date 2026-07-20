import { defineConfig } from 'vite';
import { resolve } from 'path';
import { readdirSync, existsSync, copyFileSync, mkdirSync } from 'fs';
import { join, extname } from 'path';

/**
 * Plugin to copy static assets (fonts, PDF worker) after build.
 */
function copyAssetsPlugin() {
    return {
        name: 'copy-assets',
        closeBundle() {
            const fontSrc = 'node_modules/@fortawesome/fontawesome-free/webfonts';
            const fontDest = 'webroot/fonts';
            if (existsSync(fontSrc)) {
                mkdirSync(fontDest, { recursive: true });
                for (const file of readdirSync(fontSrc)) {
                    copyFileSync(join(fontSrc, file), join(fontDest, file));
                }
                console.log('Copied FontAwesome webfonts to webroot/fonts/');
            }

            const pdfSrc = 'node_modules/pdfjs-dist/build/pdf.worker.min.mjs';
            if (existsSync(pdfSrc)) {
                copyFileSync(pdfSrc, 'webroot/js/pdf.worker.min.mjs');
                console.log('Copied PDF.js worker to webroot/js/');
            }
        },
    };
}

export default defineConfig({
    root: '.',
    base: '/',
    publicDir: false,

    build: {
        outDir: 'webroot',
        emptyOutDir: false,
        manifest: true,
        sourcemap: true,

        rollupOptions: {
            input: {
                // Main app entry
                index: resolve(__dirname, 'assets/js/index.js'),
                // All controllers + services via glob entry
                controllers: resolve(__dirname, 'assets/js/controllers-entry.js'),
                // CSS entries
                app: resolve(__dirname, 'assets/css/app.css'),
                signin: resolve(__dirname, 'assets/css/signin.css'),
                cover: resolve(__dirname, 'assets/css/cover.css'),
                dashboard: resolve(__dirname, 'assets/css/dashboard.css'),
                waivers: resolve(__dirname, 'plugins/Waivers/assets/css/waivers.css'),
                'waiver-upload': resolve(__dirname, 'plugins/Waivers/assets/css/waiver-upload.css'),
                'workflow-designer': resolve(__dirname, 'assets/css/workflow-designer.css'),
                error: resolve(__dirname, 'assets/css/error.css'),
                'gatherings_public': resolve(__dirname, 'assets/css/gatherings_public.css'),
                drawflow: resolve(__dirname, 'node_modules/drawflow/dist/drawflow.min.css'),
            },
            output: {
                dir: 'webroot',
                entryFileNames: 'js/[name]-[hash].js',
                chunkFileNames: 'js/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    const ext = extname(assetInfo.name || '').slice(1);
                    if (['css'].includes(ext)) {
                        return 'css/[name]-[hash].[ext]';
                    }
                    if (['woff', 'woff2', 'eot', 'ttf', 'otf'].includes(ext)) {
                        return 'fonts/[name]-[hash].[ext]';
                    }
                    return 'assets/[name]-[hash].[ext]';
                },
                manualChunks(id) {
                    const corePackages = [
                        '/node_modules/bootstrap/',
                        '/node_modules/popper.js/',
                        '/node_modules/@hotwired/turbo/',
                        '/node_modules/@hotwired/stimulus/',
                    ];

                    if (corePackages.some((segment) => id.includes(segment))) {
                        return 'core';
                    }

                    return undefined;
                },
            },
        },
    },

    resolve: {
        alias: {
            '~bootstrap': resolve(__dirname, 'node_modules/bootstrap'),
        },
    },

    plugins: [
        copyAssetsPlugin(),
    ],
});
