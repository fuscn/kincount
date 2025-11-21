// src/store/modules/user.js
import { defineStore } from 'pinia'
import { updateProfile, changePassword } from '@/api/auth'

export const useUserStore = defineStore('user', {
  state: () => ({
    profile: {} // 当前登录用户可编辑资料
  }),

  actions: {
    async updateProfile(data) {
      await updateProfile(data)
      // 更新成功后刷新 auth 里的用户资料
      const auth = useAuthStore()
      await auth.refreshUser()
    },

    async changePassword(data) {
      await changePassword(data)
    }
  }
})