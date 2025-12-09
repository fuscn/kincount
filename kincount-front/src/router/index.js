import { createRouter, createWebHashHistory } from 'vue-router'  // 修改这里：createWebHistory → createWebHashHistory
import routes from './routes'
import { setupGuard } from './guard'

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),  // 修改这里：createWebHistory → createWebHashHistory
  routes
})

// 全局导航守卫
setupGuard(router)

export default router