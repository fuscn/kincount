// src/api/account.js - 更新应收账款相关API
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
  return request({
    url: `/account/records/${id}/pay`,
    method: 'post',
    data
  })
}

// 应收款项 - 根据后端代码，这个对应 receivable 方法
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

// 获取供应商列表
export function getSupplierOptions() {
  return request.get('/suppliers/options')
}

// 获取客户列表（用于下拉选择）
export function getCustomerOptions(params) {
  return request.get('/customers/options', { params })
}

// 获取应收账款统计（如果后端有单独接口）
export function getAccountReceivableStatistics() {
  return request.get('/account/records/receivable/statistics')
}

// 导出应收账款数据
export function exportAccountReceivableData(params) {
  return request.get('/account/records/receivable/export', {
    params,
    responseType: 'blob'
  })
}