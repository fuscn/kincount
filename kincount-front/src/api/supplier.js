// src/api/supplier.js
import request from '@/utils/request'

// 供应商列表
export function getSupplierList(params) {
  return request.get('/suppliers', { params })
}

// 供应商详情
export function getSupplierDetail(id) {
  return request.get(`/suppliers/${id}`)
}

// 新增供应商
export function addSupplier(data) {
  return request.post('/suppliers', data)
}

// 更新供应商（通用方法，可用于更新状态或其他字段）
export function updateSupplier(id, data) {
  return request.put(`/suppliers/${id}`, data)
}



// 删除供应商
export function deleteSupplier(id) {
  return request.delete(`/suppliers/${id}`)
}

// 下拉搜索
export function searchSupplierSelect(params) {
  return request.get('/suppliers/search/select', { params })
}

// 供应商欠款
export function getSupplierArrears(id) {
  return request.get(`/suppliers/${id}/arrears`)
}

// 更新欠款
export function updateSupplierArrears(id, data) {
  return request.post(`/suppliers/${id}/arrears`, data)
}