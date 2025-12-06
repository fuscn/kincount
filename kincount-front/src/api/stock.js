// src/api/stock.js
import request from '@/utils/request'

/* ===== 库存查询（SKU 维度） ===== */
// SKU 库存分页列表（替代原商品级）
export function getStockList(params) {
  return request({
    url: '/stocks/skus',
    method: 'get',
    params
  })
}

// 单个 SKU 在多仓库的库存分布
export function getSkuWarehouseStock(skuId) {
  return request({
    url: `/stocks/skus/${skuId}/warehouses`,
    method: 'get'
  })
}

// 单个仓库中单个 SKU 的库存详情（含成本、总量、可用量）
export function getSkuStock(skuId, warehouseId) {
  return request({
    url: `/stocks/skus/${skuId}`,
    method: 'get',
    params: { warehouse_id: warehouseId }
  })
}

// 更新 SKU 库存数量（入库/出库/调整）
export function updateSkuStock(skuId, warehouseId, quantity, type = 'adjust') {
  return request({
    url: `/stocks/skus/${skuId}`,
    method: 'put',
    data: { warehouse_id: warehouseId, quantity, type }
  })
}

// SKU 库存预警列表
export function getStockWarning(params) {
  return request({
    url: '/stocks/skus/warning',
    method: 'get',
    params
  })
}

// SKU 库存统计（总量、低库存、高库存、周转率）
export function getStockStatistics() {
  return request({
    url: '/stocks/skus/statistics',
    method: 'get'
  })
}

/* ===== 库存盘点（SKU 级） ===== */
// 盘点单列表
export function getStockTakeList(params) {
  return request({
    url: '/stock/takes',
    method: 'get',
    params
  })
}

// 盘点单详情
export function getStockTakeDetail(id) {
  return request({
    url: `/stock/takes/${id}`,
    method: 'get'
  })
}

// 创建盘点单（明细为 SKU 维度）
export function addStockTake(data) {
  return request({
    url: '/stock/takes',
    method: 'post',
    data
  })
}

// 编辑盘点单
export function updateStockTake(id, data) {
  return request({
    url: `/stock/takes/${id}`,
    method: 'put',
    data
  })
}

// 删除盘点单
export function deleteStockTake(id) {
  return request({
    url: `/stock/takes/${id}`,
    method: 'delete'
  })
}

// 审核盘点单（库存差异生效）
export function auditStockTake(id) {
  return request({
    url: `/stock/takes/${id}/audit`,
    method: 'post'
  })
}

// 完成盘点单
export function completeStockTake(id) {
  return request({
    url: `/stock/takes/${id}/complete`,
    method: 'post'
  })
}

// 取消盘点单
export function cancelStockTake(id) {
  return request({
    url: `/stock/takes/${id}/cancel`,
    method: 'post'
  })
}

// 获取盘点明细（SKU 维度）
export function getStockTakeItems(id) {
  return request({
    url: `/stock/takes/${id}/items`,
    method: 'get'
  })
}

// 新增盘点明细（SKU）
export function addStockTakeItem(id, data) {
  return request({
    url: `/stock/takes/${id}/items`,
    method: 'post',
    data
  })
}

// 编辑盘点明细
export function updateStockTakeItem(id, itemId, data) {
  return request({
    url: `/stock/takes/${id}/items/${itemId}`,
    method: 'put',
    data
  })
}

// 删除盘点明细
export function deleteStockTakeItem(id, itemId) {
  return request({
    url: `/stock/takes/${id}/items/${itemId}`,
    method: 'delete'
  })
}

/* ===== 库存调拨（SKU 级） ===== */
// 调拨单列表
export function getStockTransferList(params) {
  return request({
    url: '/stock/transfers',
    method: 'get',
    params
  })
}

// 调拨单详情
export function getStockTransferDetail(id) {
  return request({
    url: `/stock/transfers/${id}`,
    method: 'get'
  })
}

// 创建调拨单（SKU 维度）
export function addStockTransfer(data) {
  return request({
    url: '/stock/transfers',
    method: 'post',
    data
  })
}

// 编辑调拨单
export function updateStockTransfer(id, data) {
  return request({
    url: `/stock/transfers/${id}`,
    method: 'put',
    data
  })
}

// 删除调拨单
export function deleteStockTransfer(id) {
  return request({
    url: `/stock/transfers/${id}`,
    method: 'delete'
  })
}

// 审核调拨单（库存移动生效）
export function auditStockTransfer(id) {
  return request({
    url: `/stock/transfers/${id}/audit`,
    method: 'post'
  })
}

// 完成调拨单
export function completeStockTransfer(id) {
  return request({
    url: `/stock/transfers/${id}/complete`,
    method: 'post'
  })
}

// 取消调拨单
export function cancelStockTransfer(id) {
  return request({
    url: `/stock/transfers/${id}/cancel`,
    method: 'post'
  })
}

// 获取调拨明细（SKU 维度）
export function getStockTransferItems(id) {
  return request({
    url: `/stock/transfers/${id}/items`,
    method: 'get'
  })
}

// 新增调拨明细（SKU）
export function addStockTransferItem(id, data) {
  return request({
    url: `/stock/transfers/${id}/items`,
    method: 'post',
    data
  })
}

// 编辑调拨明细
export function updateStockTransferItem(id, itemId, data) {
  return request({
    url: `/stock/transfers/${id}/items/${itemId}`,
    method: 'put',
    data
  })
}

// 删除调拨明细
export function deleteStockTransferItem(id, itemId) {
  return request({
    url: `/stock/transfers/${id}/items/${itemId}`,
    method: 'delete'
  })
}
/**
 * 获取退货出入库单列表
 * @param {Object} params 查询参数
 * @returns {Promise}
 */
export function getReturnStockList(params) {
  return request({
    url: '/return_stocks',
    method: 'get',
    params
  })
}

// 创建退货入库单
export function createReturnStock(data) {
  return request({
    url: 'return_stocks',
    method: 'post',
    data
  })
}

// 获取退货入库单详情
export function getReturnStockDetail(id) {
  return request({
    url: `return_stocks/${id}`,
    method: 'get'
  })
}

// 审核退货入库单
export function auditReturnStock(id) {
  return request({
    url: `return_stocks/${id}/audit`,
    method: 'post'
  })
}

// 取消退货入库单
export function cancelReturnStock(id) {
  return request({
    url: `return_stocks/${id}/cancel`,
    method: 'post'
  })
}

// 获取退货入库单明细
export function getReturnStockItems(id) {
  return request({
    url: `return_stocks/${id}/items`,
    method: 'get'
  })
}