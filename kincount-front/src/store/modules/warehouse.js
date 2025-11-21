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
    async loadList(params) {
      const { list, total } = await getWarehouseList(params)
      this.list = list
      this.total = total
    },

    async loadOptions() {
      this.options = await getWarehouseOptions()
    },
    async deleteWarehouse(id) {
      try {
        const response = await deleteWarehouse(id)
        // 从本地列表中移除
        this.list = this.list.filter(item => item.id !== id)
        this.total -= 1
        return true
      } catch (error) {
        console.error('删除仓库失败:', error)
        // 将错误信息传递给组件
        throw new Error(error.message || '删除失败')
      }
    },
    async loadStatistics(id) {
      this.statistics = await getWarehouseStatistics(id)
    },

    setCurrent(row) {
      this.current = row
      if (row?.id) this.loadStatistics(row.id)
    }
  }
})