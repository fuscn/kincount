// src/api/product.js
import request from '@/utils/request'

// 创建商品聚合
export function saveProductAggregate(data) {
  return request({ url: '/products/aggregate', method: 'post', data })
}

// 更新商品聚合
export function updateProductAggregate(id, data) {
  return request({ url: `/products/${id}/aggregate`, method: 'put', data })
}

/* ====== SKU 维度：核心接口 ====== */
// SKU 分页列表
export function getSkuList(params) {
  return request({
    url: '/skus',
    method: 'get',
    params
  })
}

// SKU 详情
export function getSkuDetail(skuId) {
  return request({
    url: `/skus/${skuId}`,
    method: 'get'
  })
}

// 新增 SKU
export function addSku(data) {
  return request({
    url: '/skus',
    method: 'post',
    data
  })
}

// 编辑 SKU
export function updateSku(skuId, data) {
  return request({
    url: `/skus/${skuId}`,
    method: 'put',
    data
  })
}

// 删除 SKU
export function deleteSku(skuId) {
  return request({
    url: `/skus/${skuId}`,
    method: 'delete'
  })
}

// 批量操作 SKU（上下架、删除）
export function batchSku(data) {
  return request({
    url: '/skus/batch',
    method: 'post',
    data
  })
}

// SKU 选择器搜索
export function getSkuSelectList(params) {
  return request({
    url: '/skus/select',
    method: 'get',
    params
  })
}

// SKU 选择器（下拉/搜索）- 与上面相同，可以移除重复的
export function searchSkuSelect(params) {
  return request({
    url: '/skus/select',
    method: 'get',
    params
  })
}

/* ====== 规格维度：辅助接口 ====== */
// 获取某商品下的全部 SKU（用于规格切换）
export function getProductSkus(productId) {
  return request({
    url: `/products/${productId}/skus`,
    method: 'get'
  })
}

// 根据所选规格反向定位 SKU
export function getSkuBySpecs(productId, specs) {
  return request({
    url: `/products/${productId}/sku-by-specs`,
    method: 'post',
    data: { specs }
  })
}

/* ====== 商品聚合：只读信息 ====== */
// 商品聚合信息
export function getProductAggregate(productId) {
  return request({
    url: `/products/${productId}/aggregate`,
    method: 'get'
  })
}

// 批量添加SKU - 使用现有的 batchSku 接口
export function addMultipleSku(data) {
  return batchSku(data) // 直接使用 batchSku
}

// 批量删除SKU - 修正URL路径
export function deleteMultipleSku(ids) {
  return request({
    url: '/skus/batch', // 修正为 /skus/batch
    method: 'delete',
    data: { ids }
  })
}