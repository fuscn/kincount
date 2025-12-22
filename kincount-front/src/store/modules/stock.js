// src/store/modules/stock.js
import { defineStore } from 'pinia'
import {
  getStockList, getSkuWarehouseStock, getSkuStock, updateSkuStock,
  getStockWarning, getStockStatistics,
  getStockTakeList, getStockTakeDetail, addStockTake, updateStockTake,
  deleteStockTake, auditStockTake, completeStockTake, cancelStockTake,
  getStockTakeItems, addStockTakeItem, updateStockTakeItem, deleteStockTakeItem,
  getStockTransferList, getStockTransferDetail, addStockTransfer, updateStockTransfer,
  deleteStockTransfer, auditStockTransfer, completeStockTransfer, cancelStockTransfer,
  getStockTransferItems, addStockTransferItem, updateStockTransferItem, deleteStockTransferItem,
  createReturnStock, getReturnStockDetail, auditReturnStock, cancelReturnStock,
  getReturnStockItems,getReturnStockList
} from '@/api/stock'

export const useStockStore = defineStore('stock', {
  state: () => ({
    // 实时库存
    list: [],
    total: 0,
    warningList: [],
    warningTotal: 0,
    statistics: {},
    skuWarehouseStock: {},
    currentSkuStock: {},

    // 库存盘点
    takeList: [],
    takeTotal: 0,
    currentTake: {},
    currentTakeItems: [],

    // 库存调拨
    transferList: [],
    transferTotal: 0,
    currentTransfer: {},
    currentTransferItems: [],

    // 退货入库
    currentReturnStock: {},
    returnStockItems: [],
    returnStockList: [],
    returnStockTotal: 0,
  }),

  getters: {
    // 库存统计相关 getters
    totalStockValue: (state) => state.statistics.total_value || 0,
    lowStockCount: (state) => state.statistics.low_stock_count || 0,
    highStockCount: (state) => state.statistics.high_stock_count || 0,
    turnoverRate: (state) => state.statistics.turnover_rate || 0,

    // 库存预警相关 getters
    warningCount: (state) => state.warningTotal || 0,

    // 盘点相关 getters
    pendingTakeCount: (state) => state.takeList.filter(item => item.status === 'pending').length,

    // 调拨相关 getters
    pendingTransferCount: (state) => state.transferList.filter(item => item.status === 'pending').length
  },

  actions: {
    /* ===== 实时库存 ===== */
    async loadList(params) {
      try {
        const result = await getStockList(params)

        // 处理不同的响应结构
        let listData = []
        let totalCount = 0

        if (result && result.list) {
          listData = result.list
          totalCount = result.total || 0
        } else if (result && result.data && result.data.list) {
          listData = result.data.list
          totalCount = result.data.total || 0
        } else if (Array.isArray(result)) {
          listData = result
          totalCount = result.length
        } else {
          listData = result || []
          totalCount = result?.total || 0
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

    async loadSkuWarehouseStock(skuId) {
      try {
        this.skuWarehouseStock = await getSkuWarehouseStock(skuId)
        return this.skuWarehouseStock
      } catch (error) {
        this.skuWarehouseStock = {}
        throw error
      }
    },

    async loadSkuStock(skuId, warehouseId) {
      try {
        this.currentSkuStock = await getSkuStock(skuId, warehouseId)
        return this.currentSkuStock
      } catch (error) {
        this.currentSkuStock = {}
        throw error
      }
    },

    async updateSkuStockData(skuId, warehouseId, quantity, type = 'adjust') {
      try {
        const result = await updateSkuStock(skuId, warehouseId, quantity, type)
        // 更新成功后，清空缓存的相关数据，强制下次重新加载
        this.currentSkuStock = {}
        return result
      } catch (error) {
        throw error
      }
    },

    async loadWarning(params) {
      try {
        const result = await getStockWarning(params)
        this.warningList = result?.list || []
        this.warningTotal = result?.total || 0
        return result
      } catch (error) {
        this.warningList = []
        this.warningTotal = 0
        throw error
      }
    },

    async loadStatistics() {
      try {
        const result = await getStockStatistics()
        
        // 处理不同的响应结构
        if (result && result.data) {
          this.statistics = result.data
        } else if (result && result.code === 200) {
          this.statistics = result.data || {}
        } else {
          this.statistics = result || {}
        }
        
        return this.statistics
      } catch (error) {
        this.statistics = {}
        throw error
      }
    },

    /* ===== 库存盘点 ===== */
    async loadTakeList(params) {
      try {
        const result = await getStockTakeList(params)
        
        // 处理不同的响应结构
        let listData = []
        let totalCount = 0

        if (result && result.data && result.data.list) {
          listData = result.data.list
          totalCount = result.data.total || 0
        } else if (result && result.list) {
          listData = result.list
          totalCount = result.total || 0
        } else if (Array.isArray(result)) {
          listData = result
          totalCount = result.length
        } else {
          listData = result || []
          totalCount = result?.total || 0
        }

        this.takeList = listData
        this.takeTotal = totalCount

        return { list: listData, total: totalCount }
      } catch (error) {
        this.takeList = []
        this.takeTotal = 0
        throw error
      }
    },

    async loadTakeDetail(id) {
      try {
        const response = await getStockTakeDetail(id)
        // 从响应中提取data字段
        this.currentTake = response?.data || response || {}
        return this.currentTake
      } catch (error) {
        this.currentTake = {}
        throw error
      }
    },

    async createStockTake(data) {
      try {
        const result = await addStockTake(data)
        return result
      } catch (error) {
        throw error
      }
    },

    async updateStockTakeData(id, data) {
      try {
        const result = await updateStockTake(id, data)
        // 更新成功后，重新加载当前盘点单
        if (this.currentTake.id === id) {
          await this.loadTakeDetail(id)
        }
        return result
      } catch (error) {
        throw error
      }
    },

    async deleteStockTakeData(id) {
      try {
        const result = await deleteStockTake(id)
        // 删除成功后，如果删除的是当前查看的盘点单，清空缓存
        if (this.currentTake.id === id) {
          this.currentTake = {}
          this.currentTakeItems = []
        }
        return result
      } catch (error) {
        throw error
      }
    },

    async auditTake(id) {
      try {
        await auditStockTake(id)
        await this.loadTakeDetail(id)
      } catch (error) {
        throw error
      }
    },

    async completeStockTakeData(id) {
      try {
        const result = await completeStockTake(id)
        await this.loadTakeDetail(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async cancelStockTakeData(id) {
      try {
        const result = await cancelStockTake(id)
        await this.loadTakeDetail(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async loadStockTakeItems(id) {
      try {
        this.currentTakeItems = await getStockTakeItems(id)
        return this.currentTakeItems
      } catch (error) {
        this.currentTakeItems = []
        throw error
      }
    },

    async createStockTakeItem(id, data) {
      try {
        const result = await addStockTakeItem(id, data)
        // 添加成功后，重新加载盘点明细
        await this.loadStockTakeItems(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async updateStockTakeItemData(id, itemId, data) {
      try {
        const result = await updateStockTakeItem(id, itemId, data)
        // 更新成功后，重新加载盘点明细
        await this.loadStockTakeItems(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async deleteStockTakeItemData(id, itemId) {
      try {
        const result = await deleteStockTakeItem(id, itemId)
        // 删除成功后，重新加载盘点明细
        await this.loadStockTakeItems(id)
        return result
      } catch (error) {
        throw error
      }
    },

    /* ===== 库存调拨 ===== */
    async loadTransferList(params) {
      try {
        const result = await getStockTransferList(params)
        
        // 处理不同的响应结构
        let listData = []
        let totalCount = 0

        if (result && result.data && result.data.list) {
          listData = result.data.list
          totalCount = result.data.total || 0
        } else if (result && result.list) {
          listData = result.list
          totalCount = result.total || 0
        } else if (Array.isArray(result)) {
          listData = result
          totalCount = result.length
        } else {
          listData = result || []
          totalCount = result?.total || 0
        }

        this.transferList = listData
        this.transferTotal = totalCount
        
        return { list: listData, total: totalCount }
      } catch (error) {
        this.transferList = []
        this.transferTotal = 0
        throw error
      }
    },

    async loadTransferDetail(id) {
      try {
        const response = await getStockTransferDetail(id)
        // 从响应中提取data字段
        this.currentTransfer = response?.data || response || {}
        return this.currentTransfer
      } catch (error) {
        this.currentTransfer = {}
        throw error
      }
    },

    async createStockTransfer(data) {
      try {
        const result = await addStockTransfer(data)
        return result
      } catch (error) {
        throw error
      }
    },

    async updateStockTransferData(id, data) {
      try {
        const result = await updateStockTransfer(id, data)
        // 更新成功后，重新加载当前调拨单
        if (this.currentTransfer.id === id) {
          await this.loadTransferDetail(id)
        }
        return result
      } catch (error) {
        throw error
      }
    },

    async deleteStockTransferData(id) {
      try {
        const result = await deleteStockTransfer(id)
        // 删除成功后，如果删除的是当前查看的调拨单，清空缓存
        if (this.currentTransfer.id === id) {
          this.currentTransfer = {}
          this.currentTransferItems = []
        }
        return result
      } catch (error) {
        throw error
      }
    },

    async auditTransfer(id) {
      try {
        await auditStockTransfer(id)
        await this.loadTransferDetail(id)
      } catch (error) {
        throw error
      }
    },

    async transferStockTransferData(id) {
      try {
        const result = await transferStockTransfer(id)
        await this.loadTransferDetail(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async completeStockTransferData(id) {
      try {
        const result = await completeStockTransfer(id)
        await this.loadTransferDetail(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async cancelStockTransferData(id) {
      try {
        const result = await cancelStockTransfer(id)
        await this.loadTransferDetail(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async loadStockTransferItems(id) {
      try {
        this.currentTransferItems = await getStockTransferItems(id)
        return this.currentTransferItems
      } catch (error) {
        this.currentTransferItems = []
        throw error
      }
    },

    async createStockTransferItem(id, data) {
      try {
        const result = await addStockTransferItem(id, data)
        // 添加成功后，重新加载调拨明细
        await this.loadStockTransferItems(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async updateStockTransferItemData(id, itemId, data) {
      try {
        const result = await updateStockTransferItem(id, itemId, data)
        // 更新成功后，重新加载调拨明细
        await this.loadStockTransferItems(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async deleteStockTransferItemData(id, itemId) {
      try {
        const result = await deleteStockTransferItem(id, itemId)
        // 删除成功后，重新加载调拨明细
        await this.loadStockTransferItems(id)
        return result
      } catch (error) {
        throw error
      }
    },

    /* ===== 退货入库 ===== */

    async loadReturnStockList(params) {
      try {
        const result = await getReturnStockList(params)

        // 处理不同的响应结构
        let listData = []
        let totalCount = 0

        if (result && result.list) {
          listData = result.list
          totalCount = result.total || 0
        } else if (result && result.data && result.data.list) {
          listData = result.data.list
          totalCount = result.data.total || 0
        } else if (Array.isArray(result)) {
          listData = result
          totalCount = result.length
        } else {
          listData = result || []
          totalCount = result?.total || 0
        }

        this.returnStockList = listData
        this.returnStockTotal = totalCount

        return { list: listData, total: totalCount }
      } catch (error) {
        this.returnStockList = []
        this.returnStockTotal = 0
        throw error
      }
    },

    async createReturnStockData(data) {
      try {
        const result = await createReturnStock(data)
        return result
      } catch (error) {
        throw error
      }
    },

    async loadReturnStockDetail(id) {
      try {
        const response = await getReturnStockDetail(id)
        // 修复：从响应中提取data字段
        this.currentReturnStock = response?.data || response || {}
        return this.currentReturnStock
      } catch (error) {
        this.currentReturnStock = {}
        throw error
      }
    },

    async auditReturnStockData(id) {
      try {
        const result = await auditReturnStock(id)
        await this.loadReturnStockDetail(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async cancelReturnStockData(id) {
      try {
        const result = await cancelReturnStock(id)
        await this.loadReturnStockDetail(id)
        return result
      } catch (error) {
        throw error
      }
    },

    async loadReturnStockItems(id) {
      try {
        const response = await getReturnStockItems(id)
        // 修复：从响应中提取data字段
        this.returnStockItems = response?.data || response || []
        return this.returnStockItems
      } catch (error) {
        this.returnStockItems = []
        throw error
      }
    },

    /* ===== 工具方法 ===== */
    clearCurrentTake() {
      this.currentTake = {}
      this.currentTakeItems = []
    },

    clearCurrentTransfer() {
      this.currentTransfer = {}
      this.currentTransferItems = []
    },

    clearCurrentReturnStock() {
      this.currentReturnStock = {}
      this.returnStockItems = []
    },

    reset() {
      this.list = []
      this.total = 0
      this.warningList = []
      this.warningTotal = 0
      this.statistics = {}
      this.skuWarehouseStock = {}
      this.currentSkuStock = {}

      this.takeList = []
      this.takeTotal = 0
      this.currentTake = {}
      this.currentTakeItems = []

      this.transferList = []
      this.transferTotal = 0
      this.currentTransfer = {}
      this.currentTransferItems = []

      this.currentReturnStock = {}
      this.returnStockItems = []
    }
  }
})