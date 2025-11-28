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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          this.orderList = res.data?.list || res.list || []
          this.orderTotal = res.data?.total || res.total || 0
          return res.data || res
        } else {
          throw new Error(res.msg || '加载采购订单列表失败')
        }
      } catch (error) {
        showToast(error.message || '加载采购订单列表失败')
        return null
      } finally {
        this.orderLoading = false
      }
    },

    /**
     * 获取采购订单详情
     * @param {Number|String} id - 订单ID
     */
    // src/store/modules/purchase.js

    async loadOrderDetail(id) {
      this.orderLoading = true;
      try {
        const res = await getPurchaseOrderDetail(id);
        if (res.code === 200) {
          // 确保这里一次性获取所有数据，包括 items
          this.currentOrder = res.data;
          // 如果有单独的 items 字段，设置它
          if (res.data.items) {
            this.currentOrderItems = res.data.items;
          }
          return res.data;
        }
      } catch (error) {
        console.error('加载订单详情失败:', error);
        throw error;
      } finally {
        this.orderLoading = false;
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

        // 修复：更灵活的响应结构处理
        if (res.code === 200 || res.status === 200) {
          showToast(res.msg || '新增采购订单成功')
          // 返回完整响应数据，确保包含 id
          return res.data || res
        } else {
          // 如果后端返回了 msg，使用后端的错误信息
          throw new Error(res.msg || '新增采购订单失败')
        }
      } catch (error) {
        console.error('addOrder error:', error)
        // 显示具体的错误信息
        const errorMsg = error.message || '新增采购订单失败'
        showToast(errorMsg)
        throw error // 重新抛出错误，让调用方处理
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

        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('编辑采购订单成功')
          // 编辑后刷新当前订单详情
          await this.loadOrderDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '编辑采购订单失败')
        }
      } catch (error) {
        showToast(error.message || '编辑采购订单失败')
        throw error // 重新抛出错误，让调用方处理
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('删除采购订单成功')
          // 删除后刷新订单列表（默认第一页）
          await this.loadOrderList()
          return res.data || res
        } else {
          throw new Error(res.msg || '删除采购订单失败')
        }
      } catch (error) {
        showToast(error.message || '删除采购订单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('审核采购订单成功')
          // 审核后刷新当前订单详情
          await this.loadOrderDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '审核采购订单失败')
        }
      } catch (error) {
        showToast(error.message || '审核采购订单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('取消采购订单成功')
          // 取消后刷新当前订单详情
          await this.loadOrderDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '取消采购订单失败')
        }
      } catch (error) {
        showToast(error.message || '取消采购订单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('完成采购订单成功')
          // 完成后刷新当前订单详情
          await this.loadOrderDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '完成采购订单失败')
        }
      } catch (error) {
        showToast(error.message || '完成采购订单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          this.currentOrderItems = res.data || res || []
          return res.data || res
        } else {
          throw new Error(res.msg || '加载订单明细失败')
        }
      } catch (error) {
        showToast(error.message || '加载订单明细失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('新增订单明细成功')
          // 新增后刷新订单明细
          await this.loadOrderItems(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '新增订单明细失败')
        }
      } catch (error) {
        showToast(error.message || '新增订单明细失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('编辑订单明细成功')
          // 编辑后刷新订单明细
          await this.loadOrderItems(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '编辑订单明细失败')
        }
      } catch (error) {
        showToast(error.message || '编辑订单明细失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('删除订单明细成功')
          // 删除后刷新订单明细
          await this.loadOrderItems(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '删除订单明细失败')
        }
      } catch (error) {
        showToast(error.message || '删除订单明细失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          this.stockList = res.data?.list || res.list || []
          this.stockTotal = res.data?.total || res.total || 0
          return res.data || res
        } else {
          throw new Error(res.msg || '加载采购入库单列表失败')
        }
      } catch (error) {
        showToast(error.message || '加载采购入库单列表失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          this.currentStock = res.data || res || null
          // 同时加载入库单的SKU明细
          await this.loadStockItems(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '加载采购入库单详情失败')
        }
      } catch (error) {
        showToast(error.message || '加载采购入库单详情失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('新增采购入库单成功')
          return res.data || res
        } else {
          throw new Error(res.msg || '新增采购入库单失败')
        }
      } catch (error) {
        showToast(error.message || '新增采购入库单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('编辑采购入库单成功')
          // 编辑后刷新当前入库单详情
          await this.loadStockDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '编辑采购入库单失败')
        }
      } catch (error) {
        showToast(error.message || '编辑采购入库单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('删除采购入库单成功')
          // 删除后刷新入库单列表
          await this.loadStockList()
          return res.data || res
        } else {
          throw new Error(res.msg || '删除采购入库单失败')
        }
      } catch (error) {
        showToast(error.message || '删除采购入库单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('审核采购入库单成功')
          // 审核后刷新当前入库单详情
          await this.loadStockDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '审核采购入库单失败')
        }
      } catch (error) {
        showToast(error.message || '审核采购入库单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('取消采购入库单成功')
          // 取消后刷新当前入库单详情
          await this.loadStockDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '取消采购入库单失败')
        }
      } catch (error) {
        showToast(error.message || '取消采购入库单失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          this.currentStockItems = res.data || res || []
          return res.data || res
        } else {
          throw new Error(res.msg || '加载入库明细失败')
        }
      } catch (error) {
        showToast(error.message || '加载入库明细失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('新增入库明细成功')
          // 新增后刷新入库明细
          await this.loadStockItems(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '新增入库明细失败')
        }
      } catch (error) {
        showToast(error.message || '新增入库明细失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('编辑入库明细成功')
          // 编辑后刷新入库明细
          await this.loadStockItems(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '编辑入库明细失败')
        }
      } catch (error) {
        showToast(error.message || '编辑入库明细失败')
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
        // 修复：检查返回的数据结构
        if (res.code === 200) {
          showToast('删除入库明细成功')
          // 删除后刷新入库明细
          await this.loadStockItems(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '删除入库明细失败')
        }
      } catch (error) {
        showToast(error.message || '删除入库明细失败')
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