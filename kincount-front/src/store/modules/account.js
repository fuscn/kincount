// src/store/modules/account.js
import { defineStore } from 'pinia'
import {
  getAccountRecordList, getAccountSummary,
  getAccountReceivable, getAccountPayable,
  payAccountRecord
} from '@/api/account'

export const useAccountStore = defineStore('account', {
  state: () => ({
    list: [], total: 0,          // 账款明细
    summary: {},                  // 应收/应付汇总
    receivableList: [],          // 应收账款
    payableList: [],             // 应付账款
    loading: false              // 加载状态
  }),

  actions: {
    async loadList(params) {
      const response = await getAccountRecordList(params)
      if (response.code === 200) {
        this.list = response.data.list || []
        this.total = response.data.total || 0
      }
      return response
    },

    async loadReceivable(params) {
      this.loading = true
      try {
        const response = await getAccountReceivable(params)
        if (response.code === 200) {
          this.receivableList = response.data.list || []
        }
        return response
      } finally {
        this.loading = false
      }
    },

    async loadSummary() {
      const response = await getAccountSummary()
      if (response.code === 200) {
        this.summary = response.data || {}
      }
      return response
    },

    async loadPayable(params) {
      const response = await getAccountPayable(params)
      if (response.code === 200) {
        this.payableList = response.data.list || []
      }
      return response
    },

    async payRecord(id, amount, extraData = {}) {
      const response = await payAccountRecord(id, {
        amount,
        ...extraData
      })

      if (response.code === 200) {
        // 刷新相关数据
        await this.loadSummary()
        await this.loadList({ page: 1 })

        // 如果响应中包含核销信息，可以存储或显示
        if (response.data && response.data.settlement) {
          // 可以显示核销成功信息
          console.log('核销成功:', response.data.settlement)
        }
      }

      return response
    }
  }
})