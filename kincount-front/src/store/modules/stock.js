// src/store/modules/stock.js
import { defineStore } from 'pinia'
import {
  getStockList, getStockWarning, getStockStatistics,
  getStockTakeList, getStockTakeDetail, auditStockTake,
  getStockTransferList, getStockTransferDetail, auditStockTransfer
} from '@/api/stock'

export const useStockStore = defineStore('stock', {
  state: () => ({
    list: [],
    total: 0,
    warningList: [],
    warningTotal: 0,
    statistics: {},
    takeList: [],
    takeTotal: 0,
    currentTake: {},
    transferList: [],
    transferTotal: 0,
    currentTransfer: {}
  }),

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

    async loadWarning(params) {
      try {
        const result = await getStockWarning(params)  // 调用 /stocks/warning API
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
        this.statistics = await getStockStatistics()
        return this.statistics
      } catch (error) {
        this.statistics = {}
        throw error
      }
    },

    /* ===== 盘点 ===== */
    async loadTakeList(params) {
      try {
        const result = await getStockTakeList(params)
        this.takeList = result?.list || []
        this.takeTotal = result?.total || 0
        return result
      } catch (error) {
        this.takeList = []
        this.takeTotal = 0
        throw error
      }
    },

    async loadTakeDetail(id) {
      try {
        this.currentTake = await getStockTakeDetail(id)
        return this.currentTake
      } catch (error) {
        this.currentTake = {}
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

    /* ===== 调拨 ===== */
    async loadTransferList(params) {
      try {
        const result = await getStockTransferList(params)
        this.transferList = result?.list || []
        this.transferTotal = result?.total || 0
        return result
      } catch (error) {
        this.transferList = []
        this.transferTotal = 0
        throw error
      }
    },

    async loadTransferDetail(id) {
      try {
        this.currentTransfer = await getStockTransferDetail(id)
        return this.currentTransfer
      } catch (error) {
        this.currentTransfer = {}
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
    }
  }
})