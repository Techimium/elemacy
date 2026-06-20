import path from "path"
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  base: './',
  server: {
    origin: 'http://localhost:5173',
    cors: true,
    hmr: {
      host: 'localhost'
    }
  },
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
      // Use WordPress's enqueued wp-i18n (window.wp.i18n) instead of bundling a
      // second copy, so wp_set_script_translations actually feeds the app.
      "@wordpress/i18n": path.resolve(__dirname, "./src/lib/wp-i18n.ts"),
    },
  },
  build: {
    outDir: '../assets/admin',
    // Keep hand-authored admin assets (admin-global.*, sidebar-sync.js,
    // dependency-notice.css) that live alongside the build output.
    emptyOutDir: false,
    rollupOptions: {
      input: {
        admin: path.resolve(__dirname, 'index.html'),
      },
      output: {
        entryFileNames: 'scripts/[name].js',
        chunkFileNames: 'scripts/[name].js',
        assetFileNames: 'styles/[name].[ext]',
      }
    }
  }
})
