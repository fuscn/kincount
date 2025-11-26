// src/store/modules/purchase.js
import { defineStore } from 'pinia'
import {
  // 采购订单相关API
  getPurchaseOrderList,
  getPurchaseOrderDetail,
  addPurchaseOrder,
  updatePurchaseOrder,
  deletePurchaseOrder,
  auditPurchaseOrder,
  cancelPurchaseOrder,
  completePurchaseOrder,
  getPurchaseOrderItems,
  addPurchaseOrderItem,
  updatePurchaseOrderItem,
  deletePurchaseOrderItem,
  // 采购入库相关API
  getPurchaseStockList,
  getPurchaseStockDetail,
  addPurchaseStock,
  updatePurchaseStock,
  deletePurchaseStock,
  auditPurchaseStock,
  cancelPurchaseStock,
  getPurchaseStockItems,
  addPurchaseStockItem,
  updatePurchaseStockItem,
  deletePurchaseStockItem
} from '@/api/purchase'
import { showToast } from 'vant'

// 定义采购模块的Pinia Store
export const usePurchaseStore = defineStore('purchase', {
  state: () => ({
    // 采购订单相关状态
    orderList: [], // 采购订单列表
    orderTotal: 0, // 采购订单总数（分页用）
    currentOrder: null, // 当前选中的采购订单详情
    currentOrderItems: [], // 当前订单的SKU明细列表
    // 采购入库相关状态
    stockList: [], // 采购入库单列表
    stockTotal: 0, // 采购入库单总数（分页用）
    currentStock: null, // 当前选中的采购入库单详情
    currentStockItems: [], // 当前入库单的SKU明细列表
    // 加载状态
    orderLoading: false,
    stockLoading: false
  }),
  actions: {
    /************************ 采购订单核心操作 ************************/
    /**
     * 加载采购订单列表
     * @param {Object} params - 筛选参数（分页、状态、关键词等）
     */
    async loadOrderList(params = {}) {
      this.orderLoading = true
      try {
        const res = await getPurchaseOrderList(params)
        this.orderList = res.data?.list || []
        this.orderTotal = res.data?.total || 0
        return res.data
      } catch (error) {
        showToast('加载采购订单列表失败')
        console.error('loadOrderList error:', error)
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /**
     * 获取采购订单详情
     * @param {Number|String} id - 订单ID
     */
    async loadOrderDetail(id) {
      this.orderLoading = true
      try {
        const res = await getPurchaseOrderDetail(id)
        this.currentOrder = res.data || null
        // 同时加载订单的SKU明细
        await this.loadOrderItems(id)
        return res.data
      } catch (error) {
        showToast('加载采购订单详情失败')
        console.error('loadOrderDetail error:', error)
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /**
     * 新增采购订单
     * @param {Object} data - 订单数据（含SKU明细）
     */
    async addOrder(data) {
      this.orderLoading = true
      try {
        const res = await addPurchaseOrder(data)
        showToast('新增采购订单成功')
        return res.data
      } catch (error) {
        showToast('新增采购订单失败')
        console.error('addOrder error:', error)
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /**
     * 编辑采购订单
     * @param {Number|String} id - 订单ID
     * @param {Object} data - 编辑后的订单数据
     */
    async updateOrder(id, data) {
      this.orderLoading = true
      try {
        const res = await updatePurchaseOrder(id, data)
        showToast('编辑采购订单成功')
        // 编辑后刷新当前订单详情
        await this.loadOrderDetail(id)
        return res.data
      } catch (error) {
        showToast('编辑采购订单失败')
        console.error('updateOrder error:', error)
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /**
     * 删除采购订单
     * @param {Number|String} id - 订单ID
     */
    async deleteOrder(id) {
      this.orderLoading = true
      try {
        const res = await deletePurchaseOrder(id)
        showToast('删除采购订单成功')
        // 删除后刷新订单列表（默认第一页）
        await this.loadOrderList()
        return res.data
      } catch (error) {
        showToast('删除采购订单失败')
        console.error('deleteOrder error:', error)
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /**
     * 审核采购订单
     * @param {Number|String} id - 订单ID
     */
    async auditOrder(id) {
      this.orderLoading = true
      try {
        const res = await auditPurchaseOrder(id)
        showToast('审核采购订单成功')
        // 审核后刷新当前订单详情
        await this.loadOrderDetail(id)
        return res.data
      } catch (error) {
        showToast('审核采购订单失败')
        console.error('auditOrder error:', error)
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /**
     * 取消采购订单
     * @param {Number|String} id - 订单ID
     */
    async cancelOrder(id) {
      this.orderLoading = true
      try {
        const res = await cancelPurchaseOrder(id)
        showToast('取消采购订单成功')
        // 取消后刷新当前订单详情
        await this.loadOrderDetail(id)
        return res.data
      } catch (error) {
        showToast('取消采购订单失败')
        console.error('cancelOrder error:', error)
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /**
     * 完成采购订单
     * @param {Number|String} id - 订单ID
     */
    async completeOrder(id) {
      this.orderLoading = true
      try {
        const res = await completePurchaseOrder(id)
        showToast('完成采购订单成功')
        // 完成后刷新当前订单详情
        await this.loadOrderDetail(id)
        return res.data
      } catch (error) {
        showToast('完成采购订单失败')
        console.error('completeOrder error:', error)
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /************************ 采购订单明细操作 ************************/
    /**
     * 加载采购订单的SKU明细
     * @param {Number|String} id - 订单ID
     */
    async loadOrderItems(id) {
      try {
        const res = await getPurchaseOrderItems(id)
        this.currentOrderItems = res.data || []
        return res.data
      } catch (error) {
        showToast('加载订单明细失败')
        console.error('loadOrderItems error:', error)
        return null
      }
    },

    /**
     * 新增订单SKU明细
     * @param {Number|String} id - 订单ID
     * @param {Object} data - 明细数据（sku_id、quantity、price）
     */
    async addOrderItem(id, data) {
      try {
        const res = await addPurchaseOrderItem(id, data)
        showToast('新增订单明细成功')
        // 新增后刷新订单明细
        await this.loadOrderItems(id)
        return res.data
      } catch (error) {
        showToast('新增订单明细失败')
        console.error('addOrderItem error:', error)
        return null
      }
    },

    /**
     * 编辑订单SKU明细
     * @param {Number|String} id - 订单ID
     * @param {Number|String} itemId - 明细ID
     * @param {Object} data - 编辑后的明细数据
     */
    async updateOrderItem(id, itemId, data) {
      try {
        const res = await updatePurchaseOrderItem(id, itemId, data)
        showToast('编辑订单明细成功')
        // 编辑后刷新订单明细
        await this.loadOrderItems(id)
        return res.data
      } catch (error) {
        showToast('编辑订单明细失败')
        console.error('updateOrderItem error:', error)
        return null
      }
    },

    /**
     * 删除订单SKU明细
     * @param {Number|String} id - 订单ID
     * @param {Number|String} itemId - 明细ID
     */
    async deleteOrderItem(id, itemId) {
      try {
        const res = await deletePurchaseOrderItem(id, itemId)
        showToast('删除订单明细成功')
        // 删除后刷新订单明细
        await this.loadOrderItems(id)
        return res.data
      } catch (error) {
        showToast('删除订单明细失败')
        console.error('deleteOrderItem error:', error)
        return null
      }
    },

    /************************ 采购入库核心操作 ************************/
    /**
     * 加载采购入库单列表
     * @param {Object} params - 筛选参数（分页、状态、关键词等）
     */
    async loadStockList(params = {}) {
      this.stockLoading = true
      try {
        const res = await getPurchaseStockList(params)
        this.stockList = res.data?.list || []
        this.stockTotal = res.data?.total || 0
        return res.data
      } catch (error) {
        showToast('加载采购入库单列表失败')
        console.error('loadStockList error:', error)
        return null
      } finally {
        this.stockLoading = false
      }
    },

    /**
     * 获取采购入库单详情
     * @param {Number|String} id - 入库单ID
     */
    async loadStockDetail(id) {
      this.stockLoading = true
      try {
        const res = await getPurchaseStockDetail(id)
        this.currentStock = res.data || null
        // 同时加载入库单的SKU明细
        await this.loadStockItems(id)
        return res.data
      } catch (error) {
        showToast('加载采购入库单详情失败')
        console.error('loadStockDetail error:', error)
        return null
      } finally {
        this.stockLoading = false
      }
    },

    /**
     * 新增采购入库单
     * @param {Object} data - 入库单数据（含SKU明细）
     */
    async addStock(data) {
      this.stockLoading = true
      try {
        const res = await addPurchaseStock(data)
        showToast('新增采购入库单成功')
        return res.data
      } catch (error) {
        showToast('新增采购入库单失败')
        console.error('addStock error:', error)
        return null
      } finally {
        this.stockLoading = false
      }
    },

    /**
     * 编辑采购入库单
     * @param {Number|String} id - 入库单ID
     * @param {Object} data - 编辑后的入库单数据
     */
    async updateStock(id, data) {
      this.stockLoading = true
      try {
        const res = await updatePurchaseStock(id, data)
        showToast('编辑采购入库单成功')
        // 编辑后刷新当前入库单详情
        await this.loadStockDetail(id)
        return res.data
      } catch (error) {
        showToast('编辑采购入库单失败')
        console.error('updateStock error:', error)
        return null
      } finally {
        this.stockLoading = false
      }
    },

    /**
     * 删除采购入库单
     * @param {Number|String} id - 入库单ID
     */
    async deleteStock(id) {
      this.stockLoading = true
      try {
        const res = await deletePurchaseStock(id)
        showToast('删除采购入库单成功')
        // 删除后刷新入库单列表
        await this.loadStockList()
        return res.data
      } catch (error) {
        showToast('删除采购入库单失败')
        console.error('deleteStock error:', error)
        return null
      } finally {
        this.stockLoading = false
      }
    },

    /**
     * 审核采购入库单
     * @param {Number|String} id - 入库单ID
     */
    async auditStock(id) {
      this.stockLoading = true
      try {
        const res = await auditPurchaseStock(id)
        showToast('审核采购入库单成功')
        // 审核后刷新当前入库单详情
        await this.loadStockDetail(id)
        return res.data
      } catch (error) {
        showToast('审核采购入库单失败')
        console.error('auditStock error:', error)
        return null
      } finally {
        this.stockLoading = false
      }
    },

    /**
     * 取消采购入库单
     * @param {Number|String} id - 入库单ID
     */
    async cancelStock(id) {
      this.stockLoading = true
      try {
        const res = await cancelPurchaseStock(id)
        showToast('取消采购入库单成功')
        // 取消后刷新当前入库单详情
        await this.loadStockDetail(id)
        return res.data
      } catch (error) {
        showToast('取消采购入库单失败')
        console.error('cancelStock error:', error)
        return null
      } finally {
        this.stockLoading = false
      }
    },

    /************************ 采购入库明细操作 ************************/
    /**
     * 加载采购入库单的SKU明细
     * @param {Number|String} id - 入库单ID
     */
    async loadStockItems(id) {
      try {
        const res = await getPurchaseStockItems(id)
        this.currentStockItems = res.data || []
        return res.data
      } catch (error) {
        showToast('加载入库明细失败')
        console.error('loadStockItems error:', error)
        return null
      }
    },

    /**
     * 新增入库SKU明细
     * @param {Number|String} id - 入库单ID
     * @param {Object} data - 明细数据（sku_id、quantity、price）
     */
    async addStockItem(id, data) {
      try {
        const res = await addPurchaseStockItem(id, data)
        showToast('新增入库明细成功')
        // 新增后刷新入库明细
        await this.loadStockItems(id)
        return res.data
      } catch (error) {
        showToast('新增入库明细失败')
        console.error('addStockItem error:', error)
        return null
      }
    },

    /**
     * 编辑入库SKU明细
     * @param {Number|String} id - 入库单ID
     * @param {Number|String} itemId - 明细ID
     * @param {Object} data - 编辑后的明细数据
     */
    async updateStockItem(id, itemId, data) {
      try {
        const res = await updatePurchaseStockItem(id, itemId, data)
        showToast('编辑入库明细成功')
        // 编辑后刷新入库明细
        await this.loadStockItems(id)
        return res.data
      } catch (error) {
        showToast('编辑入库明细失败')
        console.error('updateStockItem error:', error)
        return null
      }
    },

    /**
     * 删除入库SKU明细
     * @param {Number|String} id - 入库单ID
     * @param {Number|String} itemId - 明细ID
     */
    async deleteStockItem(id, itemId) {
      try {
        const res = await deletePurchaseStockItem(id, itemId)
        showToast('删除入库明细成功')
        // 删除后刷新入库明细
        await this.loadStockItems(id)
        return res.data
      } catch (error) {
        showToast('删除入库明细失败')
        console.error('deleteStockItem error:', error)
        return null
      }
    },

    /************************ 重置状态 ************************/
    /**
     * 重置采购订单相关状态
     */
    resetOrderState() {
      this.currentOrder = null
      this.currentOrderItems = []
    },

    /**
     * 重置采购入库相关状态
     */
    resetStockState() {
      this.currentStock = null
      this.currentStockItems = []
    },

    /**
     * 重置所有采购模块状态
     */
    resetAllState() {
      this.resetOrderState()
      this.resetStockState()
      this.orderList = []
      this.orderTotal = 0
      this.stockList = []
      this.stockTotal = 0
    }
  }
})