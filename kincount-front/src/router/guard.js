// src/router/guard.js
import { showFailToast } from 'vant'
import { useAuthStore } from '@/store/modules/auth'

// 默认标题
const DEFAULT_TITLE = '简库存'
// 网站名称（可配置）
const SITE_NAME = '简库存'

export function setupGuard(router) {
  router.beforeEach(async (to, from, next) => {
    // 设置页面标题（最先执行，确保任何时候都有标题）
    if (to.meta?.title) {
      document.title = to.meta.title
    } else {
      // 如果路由没有设置标题，使用默认标题
      document.title = DEFAULT_TITLE
    }

    // 允许访问的页面直接放行
    if (to.meta?.noAuth) {
      return next()
    }
    
    if (to.path === '/login') {
      // 登录页的特殊标题处理
      document.title = `登录 - ${SITE_NAME}`
      return next()
    }

    const auth = useAuthStore()
    const token = auth.token || localStorage.getItem('kintoken')

    // 如果没有 token，直接跳转到登录页
    if (!token) {
      showFailToast('请先登录')
      auth.clearAuthState()
      // 跳转到登录页前确保登录页标题正确
      document.title = `登录 - ${SITE_NAME}`
      next('/login')
      return
    }

    // 如果有 token 但 store 中没有，重新设置
    if (token && !auth.token) {
      auth.token = token
    }

    // 如果有 token 但没有用户信息，刷新用户信息
    if (token && !auth.user.id) {
      try {
        await auth.refreshUser()
        next()
      } catch (error) {
        // 只有确定是认证失败时才跳转到登录页
        if (error.message.includes('401') || error.message.includes('登录已过期')) {
          showFailToast('登录已过期，请重新登录')
          auth.clearAuthState()
          // 跳转到登录页前确保登录页标题正确
          document.title = `登录 - ${SITE_NAME}`
          next('/login')
        } else {
          // 网络错误等其他问题，不清除 token，继续导航
          next()
        }
      }
    } else {
      // 已有用户信息，直接放行
      next()
    }
  })

  // 可选：添加全局后置钩子，确保标题更新（双重保险）
  router.afterEach((to) => {
    // 再次确认标题已正确设置
    if (to.meta?.title && document.title !== to.meta.title) {
      document.title = to.meta.title
    }
  })
}