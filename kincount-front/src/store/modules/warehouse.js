// src/store/modules/warehouse.js
import { defineStore } from 'pinia'
import { getWarehouseList, getWarehouseOptions, getWarehouseStatistics, deleteWarehouse } from '@/api/warehouse'

export const useWarehouseStore = defineStore('warehouse', {
  state: () => ({
    list: [],
    total: 0,
    options: [],      // 下拉 {id,name}
    current: {},      // 当前仓库
    statistics: {}    // 当前仓库库存统计
  }),

  actions: {
    async loadList(params = {}) {
      try {
        const response = await getWarehouseList(params)
        
        // 处理不同的响应结构
        let listData = []
        let totalCount = 0
        
        if (response && response.list) {
          // 格式: { list: [], total: number }
          listData = response.list
          totalCount = response.total || 0
        } else if (response && response.data) {
          // 格式: { code: 200, data: { list: [], total: number } }
          if (response.data.list) {
            listData = response.data.list
            totalCount = response.data.total || 0
          } else {
            // 如果data直接是数组
            listData = Array.isArray(response.data) ? response.data : []
            totalCount = listData.length
          }
        } else if (Array.isArray(response)) {
          // 直接返回数组
          listData = response
          totalCount = response.length
        } else {
          listData = []
          totalCount = 0
        }
        
        this.list = listData
        this.total = totalCount
        
        return { list: listData, total: totalCount }
      } catch (error) {
        this.list = []
        this.total = 0
        throw error
      }
    },

    async loadOptions() {
      try {
        const response = await getWarehouseOptions()
        
        // 处理不同的响应结构
        let optionsData = []
        
        if (Array.isArray(response)) {
          optionsData = response
        } else if (response && response.data) {
          optionsData = Array.isArray(response.data) ? response.data : []
        } else if (response && response.list) {
          optionsData = response.list
        } else {
          optionsData = []
        }
        
        // 转换格式为 { text: name, value: id }
        this.options = optionsData.map(item => ({
          text: item.name,
          value: item.id
        }))
        return this.options
      } catch (error) {
        this.options = []
        throw error
      }
    },
    
    async deleteWarehouse(id) {
      try {
        const response = await deleteWarehouse(id)
        // 从本地列表中移除
        this.list = this.list.filter(item => item.id !== id)
        this.total -= 1
        return true
      } catch (error) {
        throw new Error(error.message || '删除失败')
      }
    },
    
    async loadStatistics(id) {
      try {
        const response = await getWarehouseStatistics(id)
        this.statistics = response.data || response || {}
        return this.statistics
      } catch (error) {
        this.statistics = {}
        throw error
      }
    },

    setCurrent(row) {
      this.current = row
      if (row?.id) this.loadStatistics(row.id)
    }
  }
})