// 订单状态
export const ORDER_STATUS = {
  1: '待审核',
  2: '已审核',
  3: '部分入库',
  4: '已完成',
  5: '已取消'
}

// 账款类型
export const ACCOUNT_TYPE = {
  1: '应收',
  2: '应付'
}

// 支付方式
export const PAY_METHODS = [
  { text: '现金', value: '现金' },
  { text: '银行卡', value: '银行卡' },
  { text: '微信支付', value: '微信支付' },
  { text: '支付宝', value: '支付宝' },
  { text: '转账', value: '转账' }
]

// 计量单位
export const UNITS = ['个', '件', '箱', '袋', '桶', '升', 'kg', '吨', '米', '㎡', 'm³']