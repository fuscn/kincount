// src/api/warehouse.js
import request from '@/utils/request'

// 仓库列表
export function getWarehouseList(params) {
  return request.get('/warehouses', { params })
}

// 仓库详情
export function getWarehouseDetail(id) {
  return request.get(`/warehouses/${id}`)
}

// 新增仓库
export function addWarehouse(data) {
  return request.post('/warehouses', data)
}

// 编辑仓库
export function updateWarehouse(id, data) {
  return request.put(`/warehouses/${id}`, data)
}

// 删除仓库
export function deleteWarehouse(id) {
  return request.delete(`/warehouses/${id}`)
}

// 仓库下拉
export function getWarehouseOptions() {
  return request.get('/warehouses/options')
}

// 仓库统计
export function getWarehouseStatistics(id) {
  return request.get(`/warehouses/${id}/statistics`)
}