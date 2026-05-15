import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// In Docker, set VITE_API_PROXY_TARGET=http://api:8000 (see docker-compose.yml).
const apiProxyTarget =
  process.env.VITE_API_PROXY_TARGET ?? 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    watch: {
      usePolling: true,
    },
    proxy: {
      '/api': {
        target: apiProxyTarget,
        changeOrigin: true,
      },
    },
  },
})
