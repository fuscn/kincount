// src/store/modules/auth.js
import { defineStore } from 'pinia'
import { login as apiLogin, logout as apiLogout, getUserInfo, updateProfile, changePassword } from '@/api/auth'
import router from '@/router'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('kintoken') || '',
    user: {},
    permissions: [], // 权限列表，如 ['*'] 或 ['product:view', 'product:add']
    roles: []        // 角色列表
  }),

  getters: {
    isLogin: (s) => !!s.token,

    // 权限检查逻辑
    hasPerm: (s) => (perm) => {

      // 如果拥有所有权限 (*)
      if (s.permissions.includes('*')) {
        return true
      }

      // 检查具体权限
      const hasPermission = s.permissions.includes(perm)
      return hasPermission
    },

    // 判断是否为管理员
    isAdmin: (s) => s.roles.includes('超级管理员') || s.roles.includes('admin')
  },

  actions: {
    async login(form) {
      try {
        const result = await apiLogin(form)
        console.log('登录响应:', result) // 添加调试日志

        // 根据实际响应结构调整获取token和user的方式
        const token = result.data?.token || result.token
        const user = result.data?.user || result.user || result.data || {}

        // console.log('提取的token:', token) // 调试日志
        // console.log('提取的user:', user) // 调试日志

        if (!token) throw new Error('登录失败：未获取到 token')

        this.token = token
        this.user = user

        // 从用户信息中提取权限和角色
        this.extractPermissionsAndRoles(user)

        localStorage.setItem('kintoken', token)
        return Promise.resolve()
      } catch (error) {
        console.error('登录错误:', error) // 调试日志
        return Promise.reject(error)
      }
    },
    async updateProfile(data) {
      await updateProfile(data)
      // 更新成功后刷新 auth 里的用户资料
      const auth = useAuthStore()
      await auth.refreshUser()
    },

    async changePassword(data) {
      await changePassword(data)
    },
    async refreshUser() {
      try {
        const result = await getUserInfo()
        const user = result.user || result.data || result || {}
        this.user = user

        // 从用户信息中提取权限和角色
        this.extractPermissionsAndRoles(user)


        return result
      } catch (error) {
        if (error.message?.includes('401')) {
          this.clearAuthState()
        }
        return Promise.reject(error)
      }
    },

    // 从用户信息中提取权限和角色
    extractPermissionsAndRoles(user) {
      // 提取权限
      if (user.permissions) {
        // 如果permissions是对象，如 {"0": "*"}，则转换为数组
        if (typeof user.permissions === 'object' && !Array.isArray(user.permissions)) {
          this.permissions = Object.values(user.permissions)
        } else if (Array.isArray(user.permissions)) {
          this.permissions = user.permissions
        } else {
          this.permissions = []
        }
      } else if (user.role && user.role.permissions) {
        // 从角色信息中提取权限
        if (typeof user.role.permissions === 'object') {
          this.permissions = Object.values(user.role.permissions)
        } else {
          this.permissions = [user.role.permissions]
        }
      } else {
        this.permissions = []
      }

      // 提取角色
      if (user.roles && Array.isArray(user.roles)) {
        this.roles = user.roles
      } else if (user.role) {
        // 如果role是对象，取name
        if (typeof user.role === 'object') {
          this.roles = [user.role.name]
        } else {
          this.roles = [user.role]
        }
      } else {
        this.roles = []
      }
    },

    // 其他方法保持不变
    async logout(silent = false) {
      try {
        const token = this.token
        this.$reset()
        localStorage.removeItem('kintoken')

        if (token) {
          try { await apiLogout() } catch { }
        }
      } finally {
        if (!silent && router.currentRoute.value.path !== '/login') {
          router.replace('/login')
        }
      }
    },

    clearAuthState() {
      this.token = ''
      this.user = {}
      this.permissions = []
      this.roles = []
      localStorage.removeItem('kintoken')
    }
  }
})