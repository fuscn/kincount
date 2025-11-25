// src/api/brand.js
import request from '@/utils/request'

// 品牌列表
export function getBrandList(params) {
  return request.get('/brands', { params })
}

// 品牌详情
export function getBrandDetail(id) {
  return request.get(`/brands/${id}`)
}

// 新增品牌
export function addBrand(data) {
  return request.post('/brands', data)
}

// 编辑品牌
export function updateBrand(id, data) {
  return request.put(`/brands/${id}`, data)
}

// 删除品牌
export function deleteBrand(id) {
  return request.delete(`/brands/${id}`)
}

// 品牌下拉列表
export function getBrandOptions() {
  return request.get('/brands/options')
}

// 批量操作
export function batchBrand(data) {
  return request.post('/brands/batch', data)
}
// 更新品牌状态
export function updateBrandStatus(id, status) {
  return request.put(`/brands/${id}/status`, { status })
}