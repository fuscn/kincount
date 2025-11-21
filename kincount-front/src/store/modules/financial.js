// src/store/modules/financial.js
import { defineStore } from 'pinia'
import {
  getFinancialRecordList, getFinancialCategories, getFinancialStatistics,
  getFinancialReportProfit, getFinancialReportCashflow
} from '@/api/financial'

export const useFinancialStore = defineStore('financial', {
  state: () => ({
    recordList: [], recordTotal: 0,
    categories: { income: {}, expense: {} }, // 下拉
    statistics: {},      // 收入/支出/利润
    profitReport: {},    // 利润表
    cashflowReport: []   // 资金流水
  }),

  actions: {
    async loadRecordList(params) {
      const { list, total } = await getFinancialRecordList(params)
      this.recordList = list
      this.recordTotal = total
    },

    async loadCategories() {
      this.categories.income = await getFinancialCategories('income')
      this.categories.expense = await getFinancialCategories('expense')
    },

    async loadStatistics(params) {
      this.statistics = await getFinancialStatistics(params)
    },

    async loadProfitReport(params) {
      this.profitReport = await getFinancialReportProfit(params)
    },

    async loadCashflowReport(params) {
      this.cashflowReport = await getFinancialReportCashflow(params)
    },
    async loadReceivable(params) {
      const result = await getAccountReceivable(params)
      this.receivableList = result.list || []
      this.receivableTotal = result.total || 0
      return result
    },

    async loadPayable(params) {
      const result = await getAccountPayable(params)
      this.payableList = result.list || []
      this.payableTotal = result.total || 0
      return result
    }
  }
})