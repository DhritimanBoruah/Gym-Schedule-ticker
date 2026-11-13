import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    root: 'static',
    base: './',
    publicDir: 'public',
    plugins: [tailwindcss()],
    build: {
        outDir: '../dist',
        emptyOutDir: true,
        sourcemap: false,
    },
});
