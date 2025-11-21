// src/api/category.js
import request from '@/utils/request'

// 分类列表（树形）
export function getCategoryList(params) {
  return request.get('/categories', { params })
}
// 获取品牌下拉选项（新增）
export function getBrandOptions() {
  return request({
    url: '/brands/options',
    method: 'get'
  })
}
// 分类详情
export function getCategoryDetail(id) {
  return request.get(`/categories/${id}`)
}

// 新增分类
export function addCategory(data) {
  return request.post('/categories', data)
}

// 编辑分类
export function updateCategory(id, data) {
  return request.put(`/categories/${id}`, data)
}
// 更新分类状态
export function updateCategoryStatus(id, status) {
  return request.put(`/categories/${id}`, { status })
}
// 删除分类
export function deleteCategory(id) {
  return request.delete(`/categories/${id}`)
}

// 分类树（下拉,弃用)
export function getCategoryTree() {
  return request.get('/categories/tree')
}

// 分类选项
export function getCategoryOptions() {
  return request.get('/categories/options')
}