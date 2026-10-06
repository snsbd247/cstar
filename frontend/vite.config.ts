import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const backend = 'http://127.0.0.1:8000'

// Dev: Vite proxies /api and /sanctum to Laravel so the SPA and API share one origin
// (Sanctum cookie auth, no CORS). Build: output goes into Laravel's public/spa folder,
// so production needs no Node.js — Laravel/Apache serve the static files.
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/spa/' : '/',
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    proxy: {
      '/api': backend,
      '/sanctum': backend,
      '/storage': backend,
    },
  },
  build: {
    outDir: '../backend/public/spa',
    emptyOutDir: true,
  },
}))
