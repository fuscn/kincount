export default [
  {
    path: 'purchase/order',
    name: 'PurchaseOrder',
    component: () => import('@/views/purchase/order/Index.vue'),
    meta: { title: '采购订单', showTabbar: false }
  },
  {
    path: 'purchase/order/create',
    name: 'PurchaseOrderCreate',
    component: () => import('@/views/purchase/order/OrderForm.vue'),
    meta: { title: '新建采购订单', showTabbar: false }
  },
  {
    path: '/purchase/order/edit/:id',
    name: 'PurchaseOrderEdit',
    component: () => import('@/views/purchase/order/OrderForm.vue'),
    meta: { title: '编辑采购订单', showTabbar: false }
  },
  {
    path: 'purchase/order/detail/:id',
    name: 'PurchaseOrderDetail',
    component: () => import('@/views/purchase/order/OrderDetail.vue'),
    meta: { title: '采购订单详情', showTabbar: false }
  },
  {
    path: 'purchase/stock',
    name: 'PurchaseStock',
    component: () => import('@/views/purchase/stock/Index.vue'),
    meta: { title: '采购入库' }
  },
  {
    path: 'purchase/stock/create',
    name: 'PurchaseStockCreate',
    component: () => import('@/views/Placeholder.vue'),
    meta: { title: '新建采购入库', showTabbar: false }
  },
  {
    path: 'purchase/stock/detail/:id',
    name: 'PurchaseStockDetail',
    component: () => import('@/views/purchase/stock/StockDetail.vue'),
    meta: { title: '采购入库详情', showTabbar: false }
  },
  {
    path: 'purchase/return',
    name: 'PurchaseReturn',
    component: () => import('@/views/purchase/return/RetunIndex.vue'),
    meta: { title: '采购退货', showTabbar: false }
  },
  {
    path: 'purchase/return/create',
    name: 'PurchaseReturnCreate',
    component: () => import('@/views/purchase/return/RetunForm.vue'),
    meta: { title: '新建采购退货' }
  },
  {
    path: 'purchase/return/detail/:id',
    name: 'PurchaseReturnDetail',
    component: () => import('@/views/purchase/return/RetunDetail.vue'),
    meta: { title: '采购退货详情', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'purchase/return/storage',
    name: 'PurchaseReturnStorage',
    component: () => import('@/views/purchase/return/StorageIndex.vue'),
    meta: { title: '退货出库单', showTabbar: false }
  },
  {
    path: 'purchase/return/storage/create',
    name: 'PurchaseReturnStorageCreate',
    component: () => import('@/views/Placeholder.vue'),
    meta: { title: '新建退货出库单' }
  },
  {
    path: 'purchase/return/storage/detail/:id',
    name: 'PurchaseReturnStorageDetail',
    component: () => import('@/views/purchase/return/StorageDetail.vue'),
    meta: { title: '退货出库单详情', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'purchase/return/storage/edit/:id',
    name: 'PurchaseReturnStorageEdit',
    component: () => import('@/views/Placeholder.vue'),
    meta: { title: '编辑退货出库单' }
  }
]