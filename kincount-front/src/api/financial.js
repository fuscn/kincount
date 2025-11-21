// src/api/financial.js
import request from '@/utils/request'

/* ===== 财务收支 ===== */
// 收支列表
export function getFinancialRecordList(params) {
  return request.get('/financial/records', { params })
}

// 收支详情
export function getFinancialRecordDetail(id) {
  return request.get(`/financial/records/${id}`)
}

// 新增收支
export function addFinancialRecord(data) {
  return request.post('/financial/records', data)
}

// 更新收支记录（用于编辑页面）
export function updateFinancialRecord(id, data) {
  return request.put(`/financial/records/${id}`, data)
}

// 删除收支
export function deleteFinancialRecord(id) {
  return request.delete(`/financial/records/${id}`)
}

// 收支类别
export function getFinancialCategories(type) {
  return request.get('/financial/records/categories', { params: { type } })
}

// 收支统计
export function getFinancialStatistics(params) {
  return request.get('/financial/records/statistics', { params })
}

/* ===== 财务报表 ===== */
// 利润报表
export function getFinancialReportProfit(params) {
  return request.get('/financial/reports/profit', { params })
}
// 应付款项
export function getAccountPayable(params) {
  return request.get('/account/records/payable', { params })
}

// 财务概览统计
export function getFinancialOverview() {
  return request.get('/financial/overview')
}
// 资金流水
export function getFinancialReportCashflow(params) {
  return request.get('/financial/reports/cashflow', { params })
}
// 应收款项
export function getAccountReceivable(params) {
  return request.get('/account/records/receivable', { params })
}
// 销售报表
export function getFinancialReportSales(params) {
  return request.get('/financial/reports/sales', { params })
}

// 采购报表
export function getFinancialReportPurchase(params) {
  return request.get('/financial/reports/purchase', { params })
}

// 库存报表
export function getFinancialReportInventory() {
  return request.get('/financial/reports/inventory')
}