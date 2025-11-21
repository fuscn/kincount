// src/api/customer.js
import request from '@/utils/request'

// 客户列表
export function getCustomerList(params) {
  return request.get('/customers', { params })
}

// 客户详情
export function getCustomerDetail(id) {
  return request.get(`/customers/${id}`)
}

// 新增客户
export function addCustomer(data) {
  return request.post('/customers', data)
}

// 编辑客户
export function updateCustomer(id, data) {
  return request.put(`/customers/${id}`, data)
}

// 删除客户
export function deleteCustomer(id) {
  return request.delete(`/customers/${id}`)
}

// 下拉搜索
export function searchCustomerSelect(params) {
  return request.get('/customers/search/select', { params })
}

// 客户欠款
export function getCustomerArrears(id) {
  return request.get(`/customers/${id}/arrears`)
}

// 更新欠款
export function updateCustomerArrears(id, data) {
  return request.post(`/customers/${id}/arrears`, data)
}

// 客户统计
export function getCustomerStatistics() {
  return request.get('/customers/statistics')
}