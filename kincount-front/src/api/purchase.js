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
// 基础操作：退货单CRUD
export function getPurchaseReturnList(params) {
  return request({
    url: '/returns',
    method: 'get',
    params
  })
}

export function getPurchaseReturnDetail(id) {
  return request({
    url: `/returns/${id}`,
    method: 'get'
  })
}

export function addPurchaseReturn(data) {
  return request({
    url: '/returns',
    method: 'post',
    data: {
      ...data,
      type: 2, // 采购退货类型标识（与销售退货type=1区分）
      supplier_id: data.supplier_id, // 供应商ID（采购退货必填）
      purchase_order_id: data.purchase_order_id, // 关联采购订单ID
      items: data.items.map(i => ({
        sku_id: i.sku_id,
        return_quantity: i.return_quantity,
        price: i.price,
        reason: i.reason || '', // 退货原因（采购退货特有）
        remark: i.remark || ''
      }))
    }
  })
}

export function updatePurchaseReturn(id, data) {
  return request({
    url: `/returns/${id}`,
    method: 'put',
    data: {
      ...data,
      supplier_id: data.supplier_id,
      purchase_order_id: data.purchase_order_id,
      items: data.items.map(i => ({
        id: i.id || '', // 明细ID（编辑时必填）
        sku_id: i.sku_id,
        return_quantity: i.return_quantity,
        price: i.price,
        reason: i.reason || '',
        remark: i.remark || ''
      }))
    }
  })
}

export function deletePurchaseReturn(id) {
  return request({
    url: `/returns/${id}`,
    method: 'delete'
  })
}

// 流程操作：审核/取消/完成
export function auditPurchaseReturn(id) {
  return request({
    url: `/returns/${id}/audit`,
    method: 'post'
  })
}

export function cancelPurchaseReturn(id) {
  return request({
    url: `/returns/${id}/cancel`,
    method: 'post'
  })
}

export function completePurchaseReturn(id) {
  return request({
    url: `/returns/${id}/complete`,
    method: 'post'
  })
}

// 明细管理：单品级操作
export function getPurchaseReturnItems(id) {
  return request({
    url: `/returns/${id}/items`,
    method: 'get'
  })
}

export function addPurchaseReturnItem(id, data) {
  return request({
    url: `/returns/${id}/items`,
    method: 'post',
    data: {
      sku_id: data.sku_id,
      return_quantity: data.return_quantity,
      price: data.price,
      reason: data.reason || '', // 退货原因（必填）
      remark: data.remark || ''
    }
  })
}

export function updatePurchaseReturnItem(returnId, itemId, data) {
  return request({
    url: `/returns/${returnId}/items/${itemId}`,
    method: 'put',
    data: {
      sku_id: data.sku_id,
      return_quantity: data.return_quantity,
      price: data.price,
      reason: data.reason || '',
      remark: data.remark || ''
    }
  })
}

export function deletePurchaseReturnItem(returnId, itemId) {
  return request({
    url: `/returns/${returnId}/items/${itemId}`,
    method: 'delete'
  })
}

// 款项处理：退款操作与记录查询（采购退货为供应商退款给我方）
export function refundPurchaseReturn(id, data) {
  return request({
    url: `/returns/${id}/refund`,
    method: 'post',
    data: {
      amount: data.amount, // 退款金额（应退金额）
      payment_method: data.payment_method, // 收款方式（1-现金，2-银行转账等）
      refund_time: data.refund_time || new Date().toISOString(), // 退款时间
      operator_id: data.operator_id, // 操作人ID
      receipt_no: data.receipt_no || '', // 收款凭证号（可选）
      remark: data.remark || ''
    }
  })
}

export function getPurchaseReturnRefunds(id) {
  return request({
    url: `/returns/${id}/refunds`,
    method: 'get'
  })
}

// 出入库相关：库存记录与出库单创建（采购退货需从我方仓库出库）
export function getPurchaseReturnStocks(id) {
  return request({
    url: `/returns/${id}/stocks`,
    method: 'get'
  })
}

export function createPurchaseReturnStock(id, data) {
  return request({
    url: `/returns/${id}/create_stock`,
    method: 'post',
    data: {
      warehouse_id: data.warehouse_id, // 出库仓库ID
      operator_id: data.operator_id, // 操作人ID
      stock_out_time: data.stock_out_time || new Date().toISOString(), // 出库时间
      remark: data.remark || '',
      items: data.items.map(i => ({
        sku_id: i.sku_id,
        quantity: i.quantity, // 出库数量（与退货数量一致）
        batch_no: i.batch_no || '', // 批次号（可选）
        production_date: i.production_date || '', // 生产日期（可选）
        expire_date: i.expire_date || '' // 过期日期（可选）
      }))
    }
  })
}