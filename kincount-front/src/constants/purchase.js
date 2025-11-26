// src/constants/purchase.js

// 采购订单状态常量
export const PURCHASE_ORDER_STATUS = {
  1: '待审核',
  2: '已审核',
  3: '采购中',
  4: '已完成',
  5: '已取消',
  6: '部分入库'
}

// 采购订单状态标签类型
export const PURCHASE_ORDER_TAG_TYPE = {
  1: 'warning', // 待审核
  2: 'primary', // 已审核
  3: 'info',    // 采购中
  4: 'success', // 已完成
  5: 'danger'   // 已取消
}

// 采购订单类型
export const PURCHASE_ORDER_TYPE = {
  1: '标准采购',
  2: '紧急采购',
  3: '合同采购'
}

export default {
  PURCHASE_ORDER_STATUS,
  PURCHASE_ORDER_TAG_TYPE,
  PURCHASE_ORDER_TYPE
}