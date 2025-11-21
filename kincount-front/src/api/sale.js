// src/api/sale.js
import request from '@/utils/request'

/* ===== 销售订单（SKU 维度） ===== */
// 订单列表
export function getSaleOrderList(params) {
  return request({ url: '/sale/orders', method: 'get', params })
}

// 订单详情
export function getSaleOrderDetail(id) {
  return request({ url: `/sale/orders/${id}`, method: 'get' })
}

// 新增订单（明细仅 skuId）
export function addSaleOrder(data) {
  return request({
    url: '/sale/orders',
    method: 'post',
    data: {
      ...data,
      items: data.items.map(i => ({
        sku_id: i.sku_id,
        quantity: i.quantity,
        price: i.price
      }))
    }
  })
}

// 编辑订单
export function updateSaleOrder(id, data) {
  return request({
    url: `/sale/orders/${id}`,
    method: 'put',
    data: {
      ...data,
      items: data.items.map(i => ({
        sku_id: i.sku_id,
        quantity: i.quantity,
        price: i.price
      }))
    }
  })
}

// 删除订单
export function deleteSaleOrder(id) {
  return request({ url: `/sale/orders/${id}`, method: 'delete' })
}

// 审核/取消/完成
export function auditSaleOrder(id) {
  return request({ url: `/sale/orders/${id}/audit`, method: 'post' })
}
export function cancelSaleOrder(id) {
  return request({ url: `/sale/orders/${id}/cancel`, method: 'post' })
}
export function completeSaleOrder(id) {
  return request({ url: `/sale/orders/${id}/complete`, method: 'post' })
}

// 订单明细（SKU 级）
export function getSaleOrderItems(id) {
  return request({ url: `/sale/orders/${id}/items`, method: 'get' })
}

// 增删改明细（SKU）
export function addSaleOrderItem(id, data) {
  return request({
    url: `/sale/orders/${id}/items`,
    method: 'post',
    data: { sku_id: data.sku_id, quantity: data.quantity, price: data.price }
  })
}
export function updateSaleOrderItem(id, itemId, data) {
  return request({
    url: `/sale/orders/${id}/items/${itemId}`,
    method: 'put',
    data: { sku_id: data.sku_id, quantity: data.quantity, price: data.price }
  })
}
export function deleteSaleOrderItem(id, itemId) {
  return request({ url: `/sale/orders/${id}/items/${itemId}`, method: 'delete' })
}

/* ===== 销售出库（SKU 维度） ===== */
export function getSaleStockList(params) {
  return request({ url: '/sale/stocks', method: 'get', params })
}
export function getSaleStockDetail(id) {
  return request({ url: `/sale/stocks/${id}`, method: 'get' })
}

// 新增出库（SKU）
export function addSaleStock(data) {
  return request({
    url: '/sale/stocks',
    method: 'post',
    data: {
      ...data,
      items: data.items.map(i => ({
        sku_id: i.sku_id,
        quantity: i.quantity,
        price: i.price
      }))
    }
  })
}

// 编辑出库
export function updateSaleStock(id, data) {
  return request({
    url: `/sale/stocks/${id}`,
    method: 'put',
    data: {
      ...data,
      items: data.items.map(i => ({
        sku_id: i.sku_id,
        quantity: i.quantity,
        price: i.price
      }))
    }
  })
}

export function deleteSaleStock(id) {
  return request({ url: `/sale/stocks/${id}`, method: 'delete' })
}

// 审核/完成/取消
export function auditSaleStock(id) {
  return request({ url: `/sale/stocks/${id}/audit`, method: 'post' })
}
export function completeSaleStock(id) {
  return request({ url: `/sale/stocks/${id}/complete`, method: 'post' })
}
export function cancelSaleStock(id) {
  return request({ url: `/sale/stocks/${id}/cancel`, method: 'post' })
}

// 出库明细（SKU）
export function getSaleStockItems(id) {
  return request({ url: `/sale/stocks/${id}/items`, method: 'get' })
}

export function addSaleStockItem(id, data) {
  return request({
    url: `/sale/stocks/${id}/items`,
    method: 'post',
    data: { sku_id: data.sku_id, quantity: data.quantity, price: data.price }
  })
}
export function updateSaleStockItem(id, itemId, data) {
  return request({
    url: `/sale/stocks/${id}/items/${itemId}`,
    method: 'put',
    data: { sku_id: data.sku_id, quantity: data.quantity, price: data.price }
  })
}
export function deleteSaleStockItem(id, itemId) {
  return request({ url: `/sale/stocks/${id}/items/${itemId}`, method: 'delete' })
}

/* ===== 销售退货（SKU 维度） ===== */
export function getSaleReturnList(params) {
  return request({ url: '/sale/returns', method: 'get', params })
}
export function getSaleReturnDetail(id) {
  return request({ url: `/sale/returns/${id}`, method: 'get' })
}

// 新增退货（SKU）
export function addSaleReturn(data) {
  return request({
    url: '/sale/returns',
    method: 'post',
    data: {
      ...data,
      items: data.items.map(i => ({
        sku_id: i.sku_id,
        quantity: i.quantity,
        price: i.price
      }))
    }
  })
}

export function auditSaleReturn(id) {
  return request({ url: `/sale/returns/${id}/audit`, method: 'post' })
}
export function cancelSaleReturn(id) {
  return request({ url: `/sale/returns/${id}/cancel`, method: 'post' })
}
export function completeSaleReturn(id) {
  return request({ url: `/sale/returns/${id}/complete`, method: 'post' })
}