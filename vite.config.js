import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Bind all interfaces so the dev server is reachable from outside the
        // container. Harmless when running natively.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            // What the *browser* connects to. Without this the plugin writes
            // the bind address (0.0.0.0) into public/hot, which no browser can
            // reach, and HMR silently fails.
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
            // Linux bind mounts propagate inotify events, so polling is off by
            // default. Set VITE_USE_POLLING=true if changes aren't picked up
            // (needed on Docker Desktop for macOS/Windows).
            usePolling: process.env.VITE_USE_POLLING === 'true',
        },
    },
});
