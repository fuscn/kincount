// src/store/modules/sale.js
import { defineStore } from 'pinia'
import {
  getSaleOrderList, getSaleOrderDetail, auditSaleOrder, addSaleOrder, updateSaleOrder, deleteSaleOrder,
  cancelSaleOrder, completeSaleOrder,
  getSaleStockList, getSaleStockDetail, auditSaleStock, addSaleStock, updateSaleStock, deleteSaleStock,
  cancelSaleStock, completeSaleStock,
  getSaleReturnList, getSaleReturnDetail, auditSaleReturn, addSaleReturn,
  cancelSaleReturn, completeSaleReturn
} from '@/api/sale'

export const useSaleStore = defineStore('sale', {
  state: () => ({
    orderList: [],
    orderTotal: 0,
    currentOrder: {},
    stockList: [],
    stockTotal: 0,
    currentStock: {},
    returnList: [], // 添加退货列表状态
    returnTotal: 0, // 添加退货总数状态
    currentReturn: {} // 添加当前退货详情状态
  }),

  actions: {
    // ===== 销售订单相关 =====
    async loadOrderList(params) {
      try {
        const response = await getSaleOrderList(params)

        // 处理响应结构
        let listData = []
        let totalCount = 0

        if (response && response.code === 200) {
          // 标准响应结构：{ code: 200, msg: "获取成功", data: { list: [], total: X } }
          if (response.data && response.data.list) {
            listData = response.data.list
            totalCount = response.data.total || 0
          }
        } else if (response && response.list) {
          // 直接返回列表结构：{ list: [], total: X }
          listData = response.list
          totalCount = response.total || 0
        } else if (Array.isArray(response)) {
          // 直接返回数组
          listData = response
          totalCount = response.length
        } else {
          listData = response || []
          totalCount = response?.total || 0
        }

        this.orderList = listData
        this.orderTotal = totalCount


        return { list: listData, total: totalCount }
      } catch (error) {
        this.orderList = []
        this.orderTotal = 0
        throw error
      }
    },

    async loadOrderDetail(id) {
      try {
        const response = await getSaleOrderDetail(id)

        // 处理不同的响应结构
        if (response && response.code === 200 && response.data) {
          this.currentOrder = response.data
        } else {
          this.currentOrder = response
        }

        return this.currentOrder
      } catch (error) {
        this.currentOrder = {}
        throw error
      }
    },

    async addOrder(data) {
      const result = await addSaleOrder(data)
      return result
    },

    async updateOrder(id, data) {
      const result = await updateSaleOrder(id, data)
      return result
    },

    async deleteOrder(id) {
      const result = await deleteSaleOrder(id)
      return result
    },

    async auditOrder(id) {
      try {
        // 调用审核API
        await auditSaleOrder(id)
        // 刷新当前订单数据
        await this.loadOrderDetail(id)
        return this.currentOrder
      } catch (error) {
        throw error
      }
    },

    async cancelOrder(id) {
      await cancelSaleOrder(id)
      await this.loadOrderDetail(id)
    },

    async completeOrder(id) {
      await completeSaleOrder(id)
      await this.loadOrderDetail(id)
    },

    // ===== 销售出库相关 =====
    async loadStockList(params) {
      try {
        const response = await getSaleStockList(params)

        // 处理不同的响应结构
        let listData = []
        let totalCount = 0

        if (response && response.code === 200) {
          // 标准响应结构
          if (response.data && response.data.list) {
            listData = response.data.list
            totalCount = response.data.total || 0
          } else if (response.data && Array.isArray(response.data)) {
            listData = response.data
            totalCount = response.data.length
          }
        } else if (response && response.list) {
          // 直接返回列表结构：{ list: [], total: X }
          listData = response.list
          totalCount = response.total || 0
        } else if (Array.isArray(response)) {
          // 直接返回数组
          listData = response
          totalCount = response.length
        } else {
          listData = response || []
          totalCount = response?.total || 0
        }

        this.stockList = listData
        this.stockTotal = totalCount


        return { list: listData, total: totalCount }
      } catch (error) {
        this.stockList = []
        this.stockTotal = 0
        throw error
      }
    },

    async loadStockDetail(id) {
      try {
        const response = await getSaleStockDetail(id)

        // 处理不同的响应结构
        if (response && response.code === 200 && response.data) {
          this.currentStock = response.data
        } else {
          this.currentStock = response
        }

        return this.currentStock
      } catch (error) {
        this.currentStock = {}
        throw error
      }
    },

    async addStock(data) {
      const result = await addSaleStock(data)
      return result
    },

    async updateStock(id, data) {
      const result = await updateSaleStock(id, data)
      return result
    },

    async deleteStock(id) {
      const result = await deleteSaleStock(id)
      return result
    },

    async auditStock(id) {
      await auditSaleStock(id)
      await this.loadStockDetail(id)
    },

    async completeStock(id) {
      await completeSaleStock(id)
      await this.loadStockDetail(id)
    },

    async cancelStock(id) {
      await cancelSaleStock(id)
      await this.loadStockDetail(id)
    },

    // ===== 销售退货相关 =====
    async loadReturnList(params) {
      try {
        const response = await getSaleReturnList(params)

        // 处理不同的响应结构
        let listData = []
        let totalCount = 0

        if (response && response.code === 200) {
          // 标准响应结构
          if (response.data && response.data.list) {
            listData = response.data.list
            totalCount = response.data.total || 0
          } else if (response.data && Array.isArray(response.data)) {
            listData = response.data
            totalCount = response.data.length
          }
        } else if (response && response.list) {
          // 直接返回列表结构：{ list: [], total: X }
          listData = response.list
          totalCount = response.total || 0
        } else if (Array.isArray(response)) {
          // 直接返回数组
          listData = response
          totalCount = response.length
        } else {
          listData = response || []
          totalCount = response?.total || 0
        }

        this.returnList = listData
        this.returnTotal = totalCount


        return { list: listData, total: totalCount }
      } catch (error) {
        this.returnList = []
        this.returnTotal = 0
        throw error
      }
    },

    async loadReturnDetail(id) {
      try {
        const response = await getSaleReturnDetail(id)

        // 处理不同的响应结构
        if (response && response.code === 200 && response.data) {
          this.currentReturn = response.data
        } else {
          this.currentReturn = response
        }

        return this.currentReturn
      } catch (error) {
        this.currentReturn = {}
        throw error
      }
    },

    // store/modules/sale.js
    async addReturn(data) {
      try {
        console.log('=== Store addReturn 开始 ===')
        console.log('Store接收到的数据:', JSON.stringify(data, null, 2))

        // 验证数据完整性
        if (!data.items || !Array.isArray(data.items) || data.items.length === 0) {
          throw new Error('退货商品不能为空')
        }

        console.log('调用addSaleReturn API函数...')
        const response = await addSaleReturn(data)

        console.log('Store接收到的API响应:', response)
        console.log('=== Store addReturn 结束 ===')

        if (response && response.code === 200) {
          // Pinia 中不需要使用 commit，直接修改 state
          // 将新创建的退货单添加到列表（如果后端返回了数据）
          if (response.data && response.data.id) {
            this.returnList = [response.data, ...this.returnList]
            this.returnTotal += 1
          }
          return response
        } else {
          const errorMsg = response?.msg || response?.message || '创建失败'
          throw new Error(errorMsg)
        }
      } catch (error) {
        console.error('Store添加退货失败:', error)
        throw error
      }
    },

    // 注意：apisale.js 中没有 updateSaleReturn 和 deleteSaleReturn 函数
    // 所以这里不提供 updateReturn 和 deleteReturn 方法

    async auditReturn(id) {
      await auditSaleReturn(id)
      await this.loadReturnDetail(id)
    },

    async completeReturn(id) {
      await completeSaleReturn(id)
      await this.loadReturnDetail(id)
    },

    async cancelReturn(id) {
      await cancelSaleReturn(id)
      await this.loadReturnDetail(id)
    }
  }
})