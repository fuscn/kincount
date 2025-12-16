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
    current: {}, // 当前查看/编辑的品牌
    loading: false,
    error: null
  }),

  getters: {
    // 可以添加一些计算属性
    enabledBrands: (state) => state.list.filter(brand => brand.status === 1),
    disabledBrands: (state) => state.list.filter(brand => brand.status === 0)
  },

  actions: {
    async loadList(params = {}) {
      this.loading = true
      this.error = null
      try {
        const response = await getBrandList(params)
        
        // 根据不同的响应格式处理数据
        let brandList = []
        let totalCount = 0
        
        if (Array.isArray(response)) {
          brandList = response
          totalCount = response.length
        } else if (response.list) {
          brandList = response.list
          totalCount = response.total
        } else if (response.data?.list) {
          brandList = response.data.list
          totalCount = response.data.total
        } else if (response.code === 200 && response.data?.list) {
          // 这是您提供的API响应格式
          brandList = response.data.list
          totalCount = response.data.total
        }
        
        // 更新 state
        this.list = brandList
        this.total = totalCount
        
        return { list: brandList, total: totalCount }
      } catch (error) {
        this.error = error.message || '加载品牌列表失败'
        console.error('加载品牌列表失败:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async loadDetail(id) {
      try {
        const response = await getBrandDetail(id)
        this.current = response.data || response
        return this.current
      } catch (error) {
        console.error('加载品牌详情失败:', error)
        throw error
      }
    },

    async addBrand(data) {
      try {
        const response = await addBrand(data)
        // 添加成功后，重新加载列表或直接添加到列表
        await this.loadList()
        return response
      } catch (error) {
        console.error('添加品牌失败:', error)
        throw error
      }
    },

    async updateBrand(id, data) {
      try {
        const response = await updateBrand(id, data)
        // 更新成功后，更新列表中的对应项
        const index = this.list.findIndex(item => item.id === id)
        if (index !== -1) {
          this.list[index] = { ...this.list[index], ...data }
        }
        return response
      } catch (error) {
        console.error('更新品牌失败:', error)
        throw error
      }
    },

    async toggleStatus(id, status) {
      try {
        await updateBrandStatus(id, status)
        // 更新本地状态
        const brand = this.list.find(item => item.id === id)
        if (brand) {
          brand.status = status
        }
      } catch (error) {
        console.error('更新品牌状态失败:', error)
        throw error
      }
    },

    async deleteRow(id) {
      try {
        await deleteBrand(id)
        // 删除本地状态
        this.list = this.list.filter(item => item.id !== id)
        this.total--
      } catch (error) {
        console.error('删除品牌失败:', error)
        throw error
      }
    },

    // 清空列表
    clearList() {
      this.list = []
      this.total = 0
    },

    // 根据ID获取品牌
    getBrandById(id) {
      return this.list.find(item => item.id == id)
    }
  }
})