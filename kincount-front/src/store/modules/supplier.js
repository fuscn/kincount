// src/store/modules/supplier.js
import { defineStore } from 'pinia'
import {
  getSupplierList,
  deleteSupplier,
  getSupplierArrears,
  updateSupplier
} from '@/api/supplier'

export const useSupplierStore = defineStore('supplier', {
  state: () => ({
    list: [],
    total: 0,
    current: {},
    arrears: 0
  }),

  actions: {
    async loadList(params) {
      try {
        const res = await getSupplierList(params)

        // 根据你的响应数据结构调整
        // 如果 request.js 已经处理了响应，res 就是 res.data
        // 否则需要 res.data
        const data = res.data || res

        this.list = data.list || []
        this.total = data.total || 0

        // 返回格式化后的数据
        return {
          list: this.list,
          total: this.total,
          page: data.page || 1,
          pageSize: data.page_size || data.pageSize || 15
        }
      } catch (error) {
        console.error('加载供应商列表失败:', error)
        throw error
      }
    },

    async updateSupplier(id, status) {
      await updateSupplier(id, status)
    },

    async deleteSupplier(id) {
      await deleteSupplier(id)
    },

    async loadArrears(id) {
      const res = await getSupplierArrears(id)
      this.arrears = res.payables || 0
    },

    setCurrent(row) {
      this.current = row
      if (row?.id) this.loadArrears(row.id)
    }
  }
})