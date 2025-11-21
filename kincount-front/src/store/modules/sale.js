// src/store/modules/sale.js
import { defineStore } from 'pinia'
import {
  getSaleOrderList, getSaleOrderDetail, auditSaleOrder,
  getSaleStockList, getSaleStockDetail, auditSaleStock,
  getSaleReturnList, getSaleReturnDetail, auditSaleReturn
} from '@/api/sale'

export const useSaleStore = defineStore('sale', {
  state: () => ({
    orderList: [], orderTotal: 0, currentOrder: {},
    stockList: [], stockTotal: 0, currentStock: {}
  }),

  actions: {
    async loadOrderList(params) {
      const { list, total } = await getSaleOrderList(params)
      this.orderList = list
      this.orderTotal = total
    },
    async loadOrderDetail(id) {
      this.currentOrder = await getSaleOrderDetail(id)
    },
    async auditOrder(id) {
      await auditSaleOrder(id)
      await this.loadOrderDetail(id)
    },

    async loadStockList(params) {
      try {
        console.log('🔄 调用销售出库列表API，参数:', params)
        const result = await getSaleStockList(params)
        console.log('📦 销售出库列表API响应:', result)

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

        this.stockList = listData
        this.stockTotal = totalCount

        console.log('✅ 处理后的销售出库数据:', this.stockList)

        return { list: listData, total: totalCount }
      } catch (error) {
        console.error('加载销售出库列表失败:', error)
        this.stockList = []
        this.stockTotal = 0
        throw error
      }
    },
    async loadStockDetail(id) {
      try {
        console.log('🔄 加载销售出库详情，ID:', id)
        const result = await getSaleStockDetail(id)
        console.log('📦 销售出库详情响应:', result)

        // 处理不同的响应结构
        if (result && result.data) {
          this.currentStock = result.data
        } else {
          this.currentStock = result
        }

        console.log('✅ 处理后的销售出库详情:', this.currentStock)
        return this.currentStock
      } catch (error) {
        console.error('加载销售出库详情失败:', error)
        this.currentStock = {}
        throw error
      }
    },
    async auditStock(id) {
      await auditSaleStock(id)
      await this.loadStockDetail(id)
    },
    /* ===== 销售退货 ===== */
    async loadReturnList(params) {
      const { list, total } = await getSaleReturnList(params)
      this.returnList = list
      this.returnTotal = total
    },

    async loadReturnDetail(id) {
      this.currentReturn = await getSaleReturnDetail(id)
    },

    async auditReturn(id) {
      await auditSaleReturn(id)
      await this.loadReturnDetail(id)
    }
  }
})