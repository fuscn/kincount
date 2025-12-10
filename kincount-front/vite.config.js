import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import Components from 'unplugin-vue-components/vite'
import { VantResolver } from '@vant/auto-import-resolver'

export default defineConfig({
  plugins: [
    vue(),
    // 按需引入 Vant 组件 + 自动创建类型声明（可选）
    Components({
      resolvers: [VantResolver()],
      dts: false // 不需要 TS 类型文件可设为 false
    })
  ],

  // 开发服务器
  server: {
    host: '0.0.0.0',    // 监听所有网络接口
    port: 5173,
    open: true,
    // 跨域代理
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:82',
        changeOrigin: true,
        rewrite: path => path.replace(/^\/api/, '/index.php/k')
      }
    }
  },

  // 路径别名（可选）
  resolve: {
    alias: {
      '@': new URL('./src', import.meta.url).pathname
    }
  },

  // 生产打包选项
  build: {
    target: 'es2015',
    outDir: 'dist',
    assetsDir: 'kincount',
    chunkSizeWarningLimit: 1024,
    // 静态资源文件名格式
    rollupOptions: {
      output: {
        assetFileNames: 'kincount/[ext]/[name]-[hash][extname]',
        chunkFileNames: 'kincount/js/[name]-[hash].js',
        entryFileNames: 'kincount/js/[name]-[hash].js',
      }
    }
  }
})