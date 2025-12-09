// src/utils/request.js
import axios from 'axios'
import { useAuthStore } from '@/store/modules/auth'
import router from '@/router'


// 最简单的环境判断
const baseURL = import.meta.env.MODE === 'development'
  ? '/api'  // 开发环境
  : '/index.php/kincount'  // 生产环境

// 创建 axios 实例
const service = axios.create({
  baseURL: baseURL,
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' }
})

let isRefreshing = false
let failedQueue = []

const processQueue = (error, token = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve(token)
    }
  })
  failedQueue = []
}

// 请求拦截器
service.interceptors.request.use(
  config => {
    const authStore = useAuthStore()

    let token = authStore.token
    if (!token) {
      token = localStorage.getItem('kintoken')
      if (token) {
        authStore.token = token
      }
    }

    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    return config
  },
  error => {
    return Promise.reject(error)
  }
)

// 响应拦截器
service.interceptors.response.use(
  response => {
    // 检查是否有新的 token
    const newToken = response.headers['new-authorization'];
    if (newToken) {
      const authStore = useAuthStore()
      authStore.token = newToken
      localStorage.setItem('kintoken', newToken)
    }

    const { code, msg, data } = response.data

    if (code === 200 || code === 0) {
      return {
        code: code,
        msg: msg,
        data: data
      }
    } else if (code === 401) {
      const authStore = useAuthStore()

      // 对于用户信息接口的401，尝试刷新token
      if (response.config.url.includes('/auth/userinfo')) {
        return handleTokenRefresh(authStore, response.config)
      } else {
        authStore.logout(true)
        return Promise.reject(new Error(msg || '登录已过期'))
      }
    } else {
      return Promise.reject(new Error(msg || '接口异常'))
    }
  },
  error => {

    const msg = error.response?.data?.msg || error.message || '网络错误'

    // 处理401错误
    if (error.response?.status === 401) {
      const authStore = useAuthStore()

      // 对于用户信息接口的401，尝试刷新token
      if (error.config?.url.includes('/auth/userinfo')) {
        return handleTokenRefresh(authStore, error.config)
      } else {
        authStore.logout(true)
      }
    }

    return Promise.reject(new Error(msg))
  }
)

// 处理 token 刷新
async function handleTokenRefresh(authStore, originalRequest) {
  if (isRefreshing) {
    return new Promise((resolve, reject) => {
      failedQueue.push({ resolve, reject })
    }).then(token => {
      originalRequest.headers.Authorization = `Bearer ${token}`
      return service(originalRequest)
    }).catch(err => {
      return Promise.reject(err)
    })
  }

  isRefreshing = true

  try {
    const newToken = await authStore.refreshToken()

    // 更新 Authorization header
    originalRequest.headers.Authorization = `Bearer ${newToken}`

    // 处理队列中的请求
    processQueue(null, newToken)

    // 重试原始请求
    return service(originalRequest)
  } catch (error) {

    // 处理队列中的请求
    processQueue(error, null)

    // 清除认证状态
    authStore.clearAuthState()
    router.replace('/login')

    return Promise.reject(error)
  } finally {
    isRefreshing = false
  }
}

export default service