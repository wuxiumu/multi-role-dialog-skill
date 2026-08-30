import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '127.0.0.1',
    port: 5173,
    proxy: {
      // 开发期代理到 backend PHP（API + 角色本地 asset）
      '/api': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
      },
      '/roles': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
      },
    },
  },
})
