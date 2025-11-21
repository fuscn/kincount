// src/api/account.js
import request from '@/utils/request'

// 账款列表
export function getAccountRecordList(params) {
  return request.get('/account/records', { params })
}

// 账款汇总
export function getAccountSummary() {
  return request.get('/account/records/summary')
}

// 支付账款
export function payAccountRecord(id, data) {
  return request.post(`/account/records/${id}/pay`, data)
}

// 应收款项
export function getAccountReceivable(params) {
  return request.get('/account/records/receivable', { params })
}

// 应付款项
export function getAccountPayable(params) {
  return request.get('/account/records/payable', { params })
}

// 账款统计
export function getAccountStatistics() {
  return request.get('/account/records/statistics')
}
// 新增账款记录
export function addAccountRecord(data) {
  return request.post('/account/records', data)
}
// 获取供应商列表（如果 searchSupplierSelect 不存在）
export function getSupplierOptions() {
  return request.get('/suppliers/options')
}