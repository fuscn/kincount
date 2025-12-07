// src/store/modules/settlement.js
import { defineStore } from 'pinia'
import * as settlementApi from '@/api/settlement'

export const useSettlementStore = defineStore('settlement', {
  state: () => ({
    settlementList: [],
    currentSettlement: null,
    settableAccounts: [],
    statistics: null,
    loading: false
  }),

  actions: {
    // 获取核销列表
    async fetchSettlementList(params) {
      this.loading = true
      try {
        const { data } = await settlementApi.getSettlementList(params)
        this.settlementList = data.list || []
        return data
      } finally {
        this.loading = false
      }
    },
    // 获取核销记录详情
    async fetchSettlementDetail(id) {
      this.loading = true
      try {
        const response = await settlementApi.getSettlementDetail(id)
        // 这里response是完整的API响应，包括code, msg, data
        this.currentSettlement = response.data // 只存储data部分
        return response // 返回完整的响应对象
      } finally {
        this.loading = false
      }
    },
    // 创建核销
    async createSettlement(data) {
      return await settlementApi.createSettlement(data)
    },

    // 批量核销
    async batchCreateSettlement(data) {
      return await settlementApi.batchCreateSettlement(data)
    },

    // 获取可核销账款
    async fetchSettableAccounts(params) {
      const { data } = await settlementApi.getSettableAccounts(params)
      this.settableAccounts = data.list || []
      return data
    },

    // 取消核销
    async cancelSettlement(id) {
      return await settlementApi.cancelSettlement(id)
    }
  }
})