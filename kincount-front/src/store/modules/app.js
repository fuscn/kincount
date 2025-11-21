// src/store/modules/app.js
import { defineStore } from 'pinia'

export const useAppStore = defineStore('app', {
  state: () => ({
    sidebarCollapse: false,   // 侧边栏折叠
    fullScreenLoading: false  // 全局整屏加载
  }),

  actions: {
    toggleSidebar() {
      this.sidebarCollapse = !this.sidebarCollapse
    },

    setLoading(flag) {
      this.fullScreenLoading = flag
    }
  }
})