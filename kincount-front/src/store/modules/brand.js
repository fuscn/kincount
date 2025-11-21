// src/store/modules/brand.js
import { defineStore } from 'pinia'
import {
  getBrandList,
  getBrandDetail,
  addBrand,
  updateBrand,
  deleteBrand,
  updateBrandStatus
} from '@/api/brand'

export const useBrandStore = defineStore('brand', {
  state: () => ({
    list: [],
    total: 0,
    current: {} // 当前查看/编辑的品牌
  }),

  actions: {
    async loadList(params = {}) {
      const res = await getBrandList(params)
      // 统一成 { list, total }
      if (Array.isArray(res)) return { list: res, total: res.length }
      if (res.list) return { list: res.list, total: res.total }
      if (res.data?.list) return { list: res.data.list, total: res.data.total }
      return { list: [], total: 0 }
    },

    async loadDetail(id) {
      this.current = await getBrandDetail(id)
      return this.current
    },

    async toggleStatus(id, status) {
      await updateBrandStatus(id, status)
    },

    async deleteRow(id) {
      await deleteBrand(id)
    }
  }
})