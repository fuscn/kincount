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
  getPurchaseStocksByOrderId,
  getPurchaseStockList,
  getPurchaseStockDetail,
  addPurchaseStock,
  updatePurchaseStock,
  deletePurchaseStock,
  auditPurchaseStock,
  cancelPurchaseStock,
  cancelAuditPurchaseStock,
  getPurchaseStockItems,
  addPurchaseStockItem,
  updatePurchaseStockItem,
  deletePurchaseStockItem,
  // 采购退货相关API（新增）
  getPurchaseReturnList,
  getPurchaseReturnDetail,
  addPurchaseReturn,
  updatePurchaseReturn,
  deletePurchaseReturn,
  auditPurchaseReturn,
  cancelPurchaseReturn,
  completePurchaseReturn,
  getPurchaseReturnItems,
  addPurchaseReturnItem,
  updatePurchaseReturnItem,
  deletePurchaseReturnItem,
  getPurchaseReturnStocks,
  createPurchaseReturnStock
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

    // 采购退货相关状态（新增）
    returnList: [], // 采购退货单列表
    returnTotal: 0, // 采购退货单总数（分页用）
    currentReturn: null, // 当前选中的采购退货单详情
    currentReturnItems: [], // 当前退货单的SKU明细列表

    // 加载状态
    orderLoading: false,
    stockLoading: false,
    returnLoading: false // 新增：退货加载状态
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
        console.log('Store - 原始API响应:', res)

        if (res.code === 200) {
          // 根据实际API响应结构调整
          let list = []
          let total = 0

          if (res.data && Array.isArray(res.data.data)) {
            // 标准分页结构：res.data.data 包含列表，res.data.total 包含总数
            list = res.data.data
            total = res.data.total || 0
          } else if (res.data && Array.isArray(res.data)) {
            // 直接数组结构
            list = res.data
            total = list.length
          } else {
            // 备用结构
            list = res.data?.list || res.list || []
            total = res.data?.total || res.total || 0
          }

          console.log('Store - 解析后的列表:', list)
          console.log('Store - 解析后的总数:', total)

          this.orderList = list
          this.orderTotal = total

          return res
        } else {
          throw new Error(res.msg || '加载采购订单列表失败')
        }
      } catch (error) {
        console.error('Store - loadOrderList error:', error)
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
        if (res.code === 200) {
          const detail = res.data || res
          this.currentStock = detail

          // 直接从详情响应中获取items，避免额外请求
          this.currentStockItems = detail.items || []

          return detail
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

    /************************ 采购退货核心操作（新增） ************************/
    /**
     * 加载采购退货单列表
     * @param {Object} params - 筛选参数（分页、状态、关键词等）
     */
    async loadReturnList(params = {}) {
      this.returnLoading = true
      try {
        const res = await getPurchaseReturnList(params)
        if (res.code === 200) {
          // 根据实际API响应结构调整
          let list = []
          let total = 0

          if (res.data && Array.isArray(res.data.data)) {
            list = res.data.data
            total = res.data.total || 0
          } else if (res.data && Array.isArray(res.data)) {
            list = res.data
            total = list.length
          } else {
            list = res.data?.list || res.list || []
            total = res.data?.total || res.total || 0
          }

          this.returnList = list
          this.returnTotal = total
          return res
        } else {
          throw new Error(res.msg || '加载采购退货单列表失败')
        }
      } catch (error) {
        console.error('Store - loadReturnList error:', error)
        showToast(error.message || '加载采购退货单列表失败')
        return null
      } finally {
        this.returnLoading = false
      }
    },

    /**
     * 获取采购退货单详情
     * @param {Number|String} id - 退货单ID
     */
    async loadReturnDetail(id) {
      this.returnLoading = true
      try {
        const res = await getPurchaseReturnDetail(id)
        if (res.code === 200) {
          const detail = res.data || res
          this.currentReturn = detail
          // 直接从详情响应中获取items
          this.currentReturnItems = detail.items || []
          return detail
        } else {
          throw new Error(res.msg || '加载采购退货单详情失败')
        }
      } catch (error) {
        showToast(error.message || '加载采购退货单详情失败')
        return null
      } finally {
        this.returnLoading = false
      }
    },

    /**
     * 新增采购退货单
     * @param {Object} data - 退货单数据（含SKU明细）
     */
    async addReturn(data) {
      this.returnLoading = true
      try {
        const res = await addPurchaseReturn(data)
        if (res.code === 200 || res.status === 200) {
          showToast(res.msg || '新增采购退货单成功')
          return res.data || res
        } else {
          throw new Error(res.msg || '新增采购退货单失败')
        }
      } catch (error) {
        console.error('addReturn error:', error)
        const errorMsg = error.message || '新增采购退货单失败'
        showToast(errorMsg)
        throw error
      } finally {
        this.returnLoading = false
      }
    },

    /**
     * 编辑采购退货单
     * @param {Number|String} id - 退货单ID
     * @param {Object} data - 编辑后的退货单数据
     */
    async updateReturn(id, data) {
      this.returnLoading = true
      try {
        const res = await updatePurchaseReturn(id, data)
        if (res.code === 200) {
          showToast('编辑采购退货单成功')
          // 编辑后刷新当前退货单详情
          await this.loadReturnDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '编辑采购退货单失败')
        }
      } catch (error) {
        showToast(error.message || '编辑采购退货单失败')
        throw error
      } finally {
        this.returnLoading = false
      }
    },

    /**
     * 删除采购退货单
     * @param {Number|String} id - 退货单ID
     */
    async deleteReturn(id) {
      this.returnLoading = true
      try {
        const res = await deletePurchaseReturn(id)
        if (res.code === 200) {
          showToast('删除采购退货单成功')
          // 删除后刷新退货单列表
          await this.loadReturnList()
          return res.data || res
        } else {
          throw new Error(res.msg || '删除采购退货单失败')
        }
      } catch (error) {
        showToast(error.message || '删除采购退货单失败')
        return null
      } finally {
        this.returnLoading = false
      }
    },

    /**
     * 审核采购退货单
     * @param {Number|String} id - 退货单ID
     */
    async auditReturn(id) {
      this.returnLoading = true
      try {
        const res = await auditPurchaseReturn(id)
        if (res.code === 200) {
          showToast('审核采购退货单成功')
          // 审核后刷新当前退货单详情
          await this.loadReturnDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '审核采购退货单失败')
        }
      } catch (error) {
        showToast(error.message || '审核采购退货单失败')
        return null
      } finally {
        this.returnLoading = false
      }
    },

    /**
     * 取消采购退货单
     * @param {Number|String} id - 退货单ID
     */
    async cancelReturn(id) {
      this.returnLoading = true
      try {
        const res = await cancelPurchaseReturn(id)
        if (res.code === 200) {
          showToast('取消采购退货单成功')
          // 取消后刷新当前退货单详情
          await this.loadReturnDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '取消采购退货单失败')
        }
      } catch (error) {
        showToast(error.message || '取消采购退货单失败')
        return null
      } finally {
        this.returnLoading = false
      }
    },

    /**
     * 完成采购退货单
     * @param {Number|String} id - 退货单ID
     */
    async completeReturn(id) {
      this.returnLoading = true
      try {
        const res = await completePurchaseReturn(id)
        if (res.code === 200) {
          showToast('完成采购退货单成功')
          // 完成后刷新当前退货单详情
          await this.loadReturnDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '完成采购退货单失败')
        }
      } catch (error) {
        showToast(error.message || '完成采购退货单失败')
        return null
      } finally {
        this.returnLoading = false
      }
    },

    /************************ 采购退货明细操作（新增） ************************/
    /**
     * 加载采购退货单的SKU明细
     * @param {Number|String} id - 退货单ID
     */
    async loadReturnItems(id) {
      try {
        const res = await getPurchaseReturnItems(id)
        if (res.code === 200) {
          this.currentReturnItems = res.data || res || []
          return res.data || res
        } else {
          throw new Error(res.msg || '加载退货明细失败')
        }
      } catch (error) {
        showToast(error.message || '加载退货明细失败')
        return null
      }
    },

    /**
     * 新增退货SKU明细
     * @param {Number|String} id - 退货单ID
     * @param {Object} data - 明细数据（sku_id、return_quantity、price、reason等）
     */
    async addReturn(data) {
      this.returnLoading = true
      try {
        const res = await addPurchaseReturn(data)
        console.log('addPurchaseReturn原始响应:', res)

        // 更灵活的响应处理
        if (res.code === 200 || res.status === 200 || res.id) {
          const successMsg = res.msg || '新增采购退货单成功'
          showToast(successMsg)

          // 返回完整响应，让调用方处理
          return res
        } else {
          const errorMsg = res.msg || '新增采购退货单失败'
          throw new Error(errorMsg)
        }
      } catch (error) {
        console.error('addReturn error:', error)
        showToast(error.message || '新增采购退货单失败')
        throw error
      } finally {
        this.returnLoading = false
      }
    },

    /**
     * 编辑退货SKU明细
     * @param {Number|String} returnId - 退货单ID
     * @param {Number|String} itemId - 明细ID
     * @param {Object} data - 编辑后的明细数据
     */
    async updateReturnItem(returnId, itemId, data) {
      try {
        const res = await updatePurchaseReturnItem(returnId, itemId, data)
        if (res.code === 200) {
          showToast('编辑退货明细成功')
          // 编辑后刷新退货明细
          await this.loadReturnItems(returnId)
          return res.data || res
        } else {
          throw new Error(res.msg || '编辑退货明细失败')
        }
      } catch (error) {
        showToast(error.message || '编辑退货明细失败')
        return null
      }
    },

    /**
     * 删除退货SKU明细
     * @param {Number|String} returnId - 退货单ID
     * @param {Number|String} itemId - 明细ID
     */
    async deleteReturnItem(returnId, itemId) {
      try {
        const res = await deletePurchaseReturnItem(returnId, itemId)
        if (res.code === 200) {
          showToast('删除退货明细成功')
          // 删除后刷新退货明细
          await this.loadReturnItems(returnId)
          return res.data || res
        } else {
          throw new Error(res.msg || '删除退货明细失败')
        }
      } catch (error) {
        showToast(error.message || '删除退货明细失败')
        return null
      }
    },

    /************************ 采购退货出入库操作（新增） ************************/
    /**
     * 获取采购退货关联的出入库单
     * @param {Number|String} id - 退货单ID
     */
    async loadReturnStocks(id) {
      try {
        const res = await getPurchaseReturnStocks(id)
        if (res.code === 200) {
          return res.data || res || []
        } else {
          throw new Error(res.msg || '加载退货出入库单失败')
        }
      } catch (error) {
        console.error('加载退货出入库单失败:', error)
        showToast(error.message || '加载退货出入库单失败')
        return []
      }
    },

    /**
     * 创建采购退货的出入库单
     * @param {Number|String} id - 退货单ID
     * @param {Object} data - 出入库单数据
     */
    async createReturnStock(id, data) {
      this.returnLoading = true
      try {
        const res = await createPurchaseReturnStock(id, data)
        if (res.code === 200) {
          showToast('创建退货出入库单成功')
          return res.data || res
        } else {
          throw new Error(res.msg || '创建退货出入库单失败')
        }
      } catch (error) {
        showToast(error.message || '创建退货出入库单失败')
        throw error
      } finally {
        this.returnLoading = false
      }
    },

    /************************ 其他操作 ************************/
    /**
     * 根据采购订单ID获取关联的入库单
     * @param {Number|String} orderId - 采购订单ID
     */
    async loadStocksByOrderId(orderId) {
      try {
        const res = await getPurchaseStocksByOrderId(orderId)
        if (res.code === 200) {
          return res.data || res || []
        } else {
          throw new Error(res.msg || '加载关联入库单失败')
        }
      } catch (error) {
        console.error('加载关联入库单失败:', error)
        showToast(error.message || '加载关联入库单失败')
        return []
      }
    },

    /**
     * 取消审核采购入库单
     * @param {Number|String} id - 入库单ID
     */
    async cancelAuditStock(id) {
      this.stockLoading = true
      try {
        const res = await cancelAuditPurchaseStock(id)
        if (res.code === 200) {
          showToast('取消审核成功')
          // 取消审核后刷新当前入库单详情
          await this.loadStockDetail(id)
          return res.data || res
        } else {
          throw new Error(res.msg || '取消审核失败')
        }
      } catch (error) {
        showToast(error.message || '取消审核失败')
        return null
      } finally {
        this.stockLoading = false
      }
    },

    /**
     * 生成采购入库单
     * @param {Object} data - 入库单数据
     */
    async generatePurchaseStock(data) {
      this.stockLoading = true
      try {
        const res = await addPurchaseStock(data)
        if (res.code === 200) {
          showToast('生成入库单成功')
          return res.data || res
        } else {
          throw new Error(res.msg || '生成入库单失败')
        }
      } catch (error) {
        showToast(error.message || '生成入库单失败')
        throw error
      } finally {
        this.stockLoading = false
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
     * 重置采购退货相关状态（新增）
     */
    resetReturnState() {
      this.currentReturn = null
      this.currentReturnItems = []
    },

    /**
     * 重置所有采购模块状态
     */
    resetAllState() {
      this.resetOrderState()
      this.resetStockState()
      this.resetReturnState()
      this.orderList = []
      this.orderTotal = 0
      this.stockList = []
      this.stockTotal = 0
      this.returnList = []
      this.returnTotal = 0
    }
  }
})