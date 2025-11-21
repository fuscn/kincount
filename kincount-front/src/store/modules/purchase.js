// src/store/modules/purchase.js
import { defineStore } from 'pinia'
import {
  getPurchaseOrderList, getPurchaseOrderDetail, auditPurchaseOrder,
  getPurchaseStockList, getPurchaseStockDetail, auditPurchaseStock
} from '@/api/purchase'

export const usePurchaseStore = defineStore('purchase', {
  state: () => ({
    orderList: [], orderTotal: 0, currentOrder: {},
    stockList: [], stockTotal: 0, currentStock: {}
  }),

  actions: {
    /* ===== 采购订单 ===== */
    async loadOrderList(params) {
      const { list, total } = await getPurchaseOrderList(params)
      this.orderList = list
      this.orderTotal = total
    },
    async loadOrderDetail(id) {
      this.currentOrder = await getPurchaseOrderDetail(id)
    },
    async auditOrder(id) {
      await auditPurchaseOrder(id)
      await this.loadOrderDetail(id) // 刷新状态
    },

    /* ===== 采购入库 ===== */
    async loadStockList(params) {
      const { list, total } = await getPurchaseStockList(params)
      this.stockList = list
      this.stockTotal = total
    },
    async loadStockDetail(id) {
      this.currentStock = await getPurchaseStockDetail(id)
    },
    async auditStock(id) {
      await auditPurchaseStock(id)
      await this.loadStockDetail(id)
    }
  }
})