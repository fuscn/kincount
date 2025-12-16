export default [
  {
    path: 'sale/order',
    name: 'SaleOrder',
    component: () => import('@/views/sale/order/OrderIndex.vue'),
    meta: { title: '销售订单' }
  },
  {
    path: 'sale/order/create',
    name: 'SaleOrderCreate',
    component: () => import('@/views/sale/order/OrderForm.vue'),
    meta: { title: '新增销售订单', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'sale/order/detail/:id',
    name: 'SaleOrderDetail',
    component: () => import('@/views/sale/order/OrderDetail.vue'),
    meta: { title: '销售订单详情', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'sale/stock',
    name: 'SaleStock',
    component: () => import('@/views/sale/stock/StockIndex.vue'),
    meta: { title: '销售出库', showTabbar: false }
  },
  {
    path: 'sale/stock/create',
    name: 'SaleStockCreate',
    component: () => import('@/views/sale/stock/StockForm.vue'),
    meta: { title: '新增销售出库', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'sale/stock/detail/:id',
    name: 'SaleStockDetail',
    component: () => import('@/views/sale/stock/StockDetail.vue'),
    meta: { title: '销售出库详情', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'sale/return',
    name: 'SaleReturn',
    component: () => import('@/views/sale/return/RetunIndex.vue'),
    meta: { title: '销售退货', showTabbar: false }
  },
  {
    path: 'sale/return/create',
    name: 'SaleReturnCreate',
    component: () => import('@/views/sale/return/RetunForm.vue'),
    meta: { title: '新建退货' }
  },
  {
    path: 'sale/return/detail/:id',
    name: 'SaleReturnDetail',
    component: () => import('@/views/sale/return/RetunDetail.vue'),
    meta: { title: '退货详情', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'sale/return/storage',
    name: 'SaleReturnStorage',
    component: () => import('@/views/sale/return/StorageIndex.vue'),
    meta: { title: '退货入库单', showTabbar: false }
  },
  {
    path: 'sale/return/storage/create',
    name: 'SaleReturnStorageCreate',
    component: () => import('@/views/Placeholder.vue'),
    meta: { title: '新建退货入库单' }
  },
  {
    path: 'sale/return/storage/detail/:id',
    name: 'SaleReturnStorageDetail',
    component: () => import('@/views/sale/return/StorageDetail.vue'),
    meta: { title: '退货入库单详情', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'sale/return/storage/edit/:id',
    name: 'SaleReturnStorageEdit',
    component: () => import('@/views/Placeholder.vue'),
    meta: { title: '编辑退货入库单' }
  }
]