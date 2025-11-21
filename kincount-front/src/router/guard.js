// src/router/guard.js
import { showFailToast } from 'vant'
import { useAuthStore } from '@/store/modules/auth'

export function setupGuard(router) {
  router.beforeEach(async (to, from, next) => {

    // 允许访问的页面直接放行
    if (to.meta?.noAuth) {
      return next()
    }
    
    if (to.path === '/login') {
      return next()
    }

    const auth = useAuthStore()
    const token = auth.token || localStorage.getItem('kintoken')
    

    // 如果没有 token，直接跳转到登录页
    if (!token) {
      showFailToast('请先登录')
      auth.clearAuthState()
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
}