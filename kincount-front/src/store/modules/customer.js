// src/store/modules/customer.js
import { defineStore } from 'pinia'
import { getCustomerList, getCustomerArrears ,deleteCustomer} from '@/api/customer'

export const useCustomerStore = defineStore('customer', {
  state: () => ({
    list: [],
    total: 0,
    current: {},      // 当前客户档案
    arrears: 0        // 当前客户欠款余额
  }),

  actions: {
    async loadList(params) {
      try {
        const response = await getCustomerList(params)
        
        // API 直接返回数据格式 { list, total, page, page_size }
        if (response && response.list) {
          this.list = response.list
          this.total = response.total
          
          // 返回数据供组件使用
          const result = {
            list: response.list,
            total: response.total
          }
          return result
        }
        
        // 如果还是标准响应格式 { code, msg, data }
        if (response && response.code === 200) {
          this.list = response.data.list || []
          this.total = response.data.total || 0
          
          const result = {
            list: response.data.list || [],
            total: response.data.total || 0
          }
          return result
        }

        // 如果直接返回数组
        if (Array.isArray(response)) {
          this.list = response
          this.total = response.length
          return {
            list: response,
            total: response.length
          }
        }

        throw new Error('无法识别的API响应格式')
        
      } catch (error) {
        throw error
      }
    },

    async loadArrears(id) {
      const response = await getCustomerArrears(id)
      // 根据实际API返回格式调整
      if (response && response.receivables !== undefined) {
        this.arrears = response.receivables
      } else if (response && response.code === 200) {
        this.arrears = response.data.receivables
      }
      return this.arrears
    },

    async toggleStatus(id, status) {
      // 这里需要调用对应的 API
      // const response = await updateCustomerStatus(id, status)
      // 模拟成功
      return Promise.resolve()
    },

    async deleteRow(id) {
      try {
        await deleteCustomer(id)
        return true
      } catch (error) {
        throw new Error('删除失败: ' + (error.message || '未知错误'))
      }
    },

    setCurrent(row) {
      this.current = row
      if (row?.id) this.loadArrears(row.id)
    }
  }
})