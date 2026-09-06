import path from "path"
import fs from "node:fs"
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

function readWpConfigDevServer() {
  const wpConfigPath = path.resolve(__dirname, '../../../..', 'wp-config.php')

  if (!fs.existsSync(wpConfigPath)) {
    return undefined
  }

  const wpConfig = fs.readFileSync(wpConfigPath, 'utf8')
  const match = wpConfig.match(/define\(\s*['"]ELEMACY_VITE_DEV_SERVER['"]\s*,\s*['"]([^'"]+)['"]\s*\)/)

  return match?.[1]
}

const DEV_SERVER_URL = process.env.ELEMACY_VITE_DEV_SERVER ?? readWpConfigDevServer() ?? 'http://localhost:5173'
const DEV_SERVER = new URL(DEV_SERVER_URL)

// https://vite.dev/config/
export default defineConfig({
  base: './',
  server: {
    origin: DEV_SERVER_URL,
    host: DEV_SERVER.hostname,
    port: Number(DEV_SERVER.port) || 5173,
    strictPort: true,
    cors: true,
    hmr: {
      host: DEV_SERVER.hostname
    }
  },
  plugins: [react(), tailwindcss()],
  // No static public/ assets belong in the WP build output (kills vite.svg).
  publicDir: false,
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
      // Entry is the module itself, not index.html, so the build emits only
      // scripts/admin.js + styles/admin.css (no dead index.html in dist).
      input: {
        admin: path.resolve(__dirname, 'src/main.tsx'),
      },
      output: {
        entryFileNames: 'scripts/[name].js',
        chunkFileNames: 'scripts/[name].js',
        assetFileNames: 'styles/[name].[ext]',
      }
    }
  }
})
