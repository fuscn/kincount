// src/store/modules/supplier.js
import { defineStore } from 'pinia'
import { 
  getSupplierList, 
  deleteSupplier,
  getSupplierArrears ,
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
      const res = await getSupplierList(params)
      // 根据你的 request.js 拦截器，这里返回的是 res.data
      this.list = res.list || []
      this.total = res.total || 0
      return res
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