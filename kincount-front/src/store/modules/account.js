// src/store/modules/account.js
import { defineStore } from 'pinia'
import {
  getAccountRecordList, getAccountSummary,
  getAccountReceivable, getAccountPayable,
  payAccountRecord
} from '@/api/account'

export const useAccountStore = defineStore('account', {
  state: () => ({
    list: [], total: 0,          // 账款明细
    summary: {},                  // 应收/应付汇总
    receivableList: [],          // 应收账款
    payableList: []              // 应付账款
  }),

  actions: {
    async loadList(params) {
      const { list, total } = await getAccountRecordList(params)
      this.list = list
      this.total = total
    },

    async loadSummary() {
      this.summary = await getAccountSummary()
    },

    async loadReceivable(params) {
      this.receivableList = await getAccountReceivable(params)
    },

    async loadPayable(params) {
      this.payableList = await getAccountPayable(params)
    },

    async payRecord(id, amount) {
      await payAccountRecord(id, { amount })
      // 刷新相关数据
      await this.loadSummary()
      await this.loadList({ page: 1 })
    }
  }
})