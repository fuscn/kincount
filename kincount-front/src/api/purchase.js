// src/api/purchase.js
import request from '@/utils/request'

/* ===== 采购订单（SKU 维度） ===== */
// 订单列表
export function getPurchaseOrderList(params) {
  return request({
    url: '/purchase/orders',
    method: 'get',
    params
  })
}
// 根据采购订单ID获取关联入库单
export const getPurchaseStocksByOrderId = (orderId) => {
  return request({
    url: '/purchase/stocks/by_order/' + orderId,
    method: 'get'
  })
}
// 订单详情
export function getPurchaseOrderDetail(id) {
  return request({
    url: `/purchase/orders/${id}`,
    method: 'get'
  })
}
export const cancelAuditPurchaseStock = (id) => {
  return request({
    url: `/purchase/stock/${id}/cancelAudit`,
    method: 'post'
  })
}
// 新增订单（明细必须含 skuId）
export function addPurchaseOrder(data) {
  return request({
    url: '/purchase/orders',
    method: 'post',
    data: {
      ...data,
      items: data.items.map(item => ({
        product_id: item.product_id,  // 添加这一行
        sku_id: item.sku_id,
        quantity: item.quantity,
        price: item.price
      }))
    }
  })
}

// 编辑订单（SKU 级）
export function updatePurchaseOrder(id, data) {
  return request({
    url: `/purchase/orders/${id}`,
    method: 'put',
    data: {
      ...data,
      items: data.items.map(item => ({
        product_id: item.product_id,  // 添加这一行
        sku_id: item.sku_id,
        quantity: item.quantity,
        price: item.price
      }))
    }
  })
}

// 删除订单
export function deletePurchaseOrder(id) {
  return request({
    url: `/purchase/orders/${id}`,
    method: 'delete'
  })
}

// 审核订单
export function auditPurchaseOrder(id) {
  return request({
    url: `/purchase/orders/${id}/audit`,
    method: 'post'
  })
}

// 取消订单
export function cancelPurchaseOrder(id) {
  return request({
    url: `/purchase/orders/${id}/cancel`,
    method: 'post'
  })
}

// 完成订单
export function completePurchaseOrder(id) {
  return request({
    url: `/purchase/orders/${id}/complete`,
    method: 'post'
  })
}

// 订单明细（SKU 维度）
export function getPurchaseOrderItems(id) {
  return request({
    url: `/purchase/orders/${id}/items`,
    method: 'get'
  })
}

// 新增订单明细（SKU）
export function addPurchaseOrderItem(id, data) {
  return request({
    url: `/purchase/orders/${id}/items`,
    method: 'post',
    data: {
      product_id: data.product_id,  // 添加这一行
      sku_id: data.sku_id,
      quantity: data.quantity,
      price: data.price
    }
  })
}

// 编辑订单明细（SKU）
export function updatePurchaseOrderItem(id, itemId, data) {
  return request({
    url: `/purchase/orders/${id}/items/${itemId}`,
    method: 'put',
    data: {
      product_id: data.product_id,  // 添加这一行
      sku_id: data.sku_id,
      quantity: data.quantity,
      price: data.price
    }
  })
}

// 删除订单明细
export function deletePurchaseOrderItem(id, itemId) {
  return request({
    url: `/purchase/orders/${id}/items/${itemId}`,
    method: 'delete'
  })
}

/* ===== 采购入库（SKU 维度） ===== */
// 入库列表
export function getPurchaseStockList(params) {
  return request({
    url: '/purchase/stocks',
    method: 'get',
    params
  })
}

// 入库详情
export function getPurchaseStockDetail(id) {
  return request({
    url: `/purchase/stocks/${id}`,
    method: 'get'
  })
}

// 新增入库（SKU 级）
export function addPurchaseStock(data) {
  return request({
    url: '/purchase/stocks',
    method: 'post',
    data: {
      ...data,
      items: data.items.map(item => ({
        product_id: item.product_id,  // 添加这一行
        sku_id: item.sku_id,
        quantity: item.quantity,
        price: item.price
      }))
    }
  })
}

// 编辑入库
export function updatePurchaseStock(id, data) {
  return request({
    url: `/purchase/stocks/${id}`,
    method: 'put',
    data: {
      ...data,
      items: data.items.map(item => ({
        product_id: item.product_id,  // 添加这一行
        sku_id: item.sku_id,
        quantity: item.quantity,
        price: item.price
      }))
    }
  })
}

// 删除入库
export function deletePurchaseStock(id) {
  return request({
    url: `/purchase/stocks/${id}`,
    method: 'delete'
  })
}

// 审核入库
export function auditPurchaseStock(id) {
  return request({
    url: `/purchase/stocks/${id}/audit`,
    method: 'post'
  })
}

// 取消入库
export function cancelPurchaseStock(id) {
  return request({
    url: `/purchase/stocks/${id}/cancel`,
    method: 'post'
  })
}

// 入库明细（SKU 维度）
export function getPurchaseStockItems(id) {
  return request({
    url: `/purchase/stocks/${id}/items`,
    method: 'get'
  })
}

// 新增入库明细（SKU）
export function addPurchaseStockItem(id, data) {
  return request({
    url: `/purchase/stocks/${id}/items`,
    method: 'post',
    data: {
      product_id: item.product_id,  // 添加这一行
      sku_id: data.sku_id,
      quantity: data.quantity,
      price: data.price
    }
  })
}

// 编辑入库明细
export function updatePurchaseStockItem(id, itemId, data) {
  return request({
    url: `/purchase/stocks/${id}/items/${itemId}`,
    method: 'put',
    data: {
      product_id: item.product_id,  // 添加这一行
      sku_id: data.sku_id,
      quantity: data.quantity,
      price: data.price
    }
  })
}

// 删除入库明细
export function deletePurchaseStockItem(id, itemId) {
  return request({
    url: `/purchase/stocks/${id}/items/${itemId}`,
    method: 'delete'
  })
}

/* ===== 采购退货（SKU 维度）===== */
// 采购退货列表（统一退货模块，通过type=1筛选采购退货）
export function getPurchaseReturnList(params) {
  return request({
    url: '/returns',
    method: 'get',
    params: {
      ...params,
      type: 1 // 采购退货类型标识（数据库定义：0-销售退货 1-采购退货）
    }
  })
}

// 采购退货详情
export function getPurchaseReturnDetail(id) {
  return request({
    url: `/returns/${id}`,
    method: 'get'
  })
}

// 新增采购退货（统一退货模块）
export function addPurchaseReturn(data) {
  return request({
    url: '/returns',
    method: 'post',
    data: {
      ...data,
      type: 1 // 采购退货类型标识（数据库定义：0-销售退货 1-采购退货）
    }
  })
}

// 编辑采购退货
export function updatePurchaseReturn(id, data) {
  return request({
    url: `/returns/${id}`,
    method: 'put',
    data: {
      ...data,
      type: 1 // 采购退货类型标识（数据库定义：0-销售退货 1-采购退货）
    }
  })
}

// 删除采购退货
export function deletePurchaseReturn(id) {
  return request({
    url: `/returns/${id}`,
    method: 'delete'
  })
}

// 审核采购退货
export function auditPurchaseReturn(id) {
  return request({
    url: `/returns/${id}/audit`,
    method: 'post'
  })
}

// 取消采购退货
export function cancelPurchaseReturn(id) {
  return request({
    url: `/returns/${id}/cancel`,
    method: 'post'
  })
}

// 完成采购退货
export function completePurchaseReturn(id) {
  return request({
    url: `/returns/${id}/complete`,
    method: 'post'
  })
}

// 获取采购退货明细
export function getPurchaseReturnItems(id) {
  return request({
    url: `/returns/${id}/items`,
    method: 'get'
  })
}

// 新增采购退货明细
export function addPurchaseReturnItem(id, data) {
  return request({
    url: `/returns/${id}/items`,
    method: 'post',
    data: {
      ...data,
      type: 1 // 采购退货类型标识（数据库定义：0-销售退货 1-采购退货）
    }
  })
}

// 编辑采购退货明细
export function updatePurchaseReturnItem(returnId, itemId, data) {
  return request({
    url: `/returns/${returnId}/items/${itemId}`,
    method: 'put',
    data: {
      ...data,
      type: 1 // 采购退货类型标识（数据库定义：0-销售退货 1-采购退货）
    }
  })
}

// 删除采购退货明细
export function deletePurchaseReturnItem(returnId, itemId) {
  return request({
    url: `/returns/${returnId}/items/${itemId}`,
    method: 'delete'
  })
}

// 获取采购退货关联的出入库单
export function getPurchaseReturnStocks(id) {
  return request({
    url: `/returns/${id}/stocks`,
    method: 'get'
  })
}

// 创建采购退货的出入库单
export function createPurchaseReturnStock(id, data) {
  return request({
    url: `/returns/${id}/create_stock`,
    method: 'post',
    data: {
      ...data,
      type: 1 // 采购退货类型标识（数据库定义：0-销售退货 1-采购退货）
    }
  })
}