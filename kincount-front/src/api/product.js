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
// SKU 分页列表（替代原商品列表）
export function getSkuList(params) {
  return request({
    url: '/skus',
    method: 'get',
    params
  })
}

// SKU 详情（替代原商品详情）
export function getSkuDetail(skuId) {
  return request({
    url: `/skus/${skuId}`,
    method: 'get'
  })
}

// 新增 SKU（含规格、价格、库存初始化）
export function addSku(data) {
  return request({
    url: '/skus',
    method: 'post',
    data
  })
}

// 编辑 SKU（价格、规格、状态等）
export function updateSku(skuId, data) {
  return request({
    url: `/skus/${skuId}`,
    method: 'put',
    data
  })
}

// 删除 SKU（软删）
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
// SKU 选择器搜索（分页 + 关键字）
export function getSkuSelectList(params) {
  return request({
    url: '/skus/select',
    method: 'get',
    params
  })
}
// SKU 选择器（下拉/搜索）
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
// 商品聚合信息（仅用于展示基础属性、图片、分类等）
export function getProductAggregate(productId) {
  return request({
    url: `/products/${productId}/aggregate`,
    method: 'get'
  })
}