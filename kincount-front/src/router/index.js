import { createRouter, createWebHistory } from 'vue-router'
import routes from './routes'
import { setupGuard } from './guard'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// 全局导航守卫
setupGuard(router)

export default router