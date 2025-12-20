export default [
  {
    path: 'stock',
    name: 'Stock',
    component: () => import('@/views/stock/Index.vue'),
    meta: { title: '库存查询' }
  },
  {
    path: 'stock/take/create',
    name: 'StockTakeCreate',
    component: () => import('@/views/stock/TakeCreate.vue'),
    meta: { title: '新建盘点', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'stock/transfer/create',
    name: 'StockTransferCreate',
    component: () => import('@/views/stock/TransferCreate.vue'),
    meta: { title: '新建调拨', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'stock/take',
    name: 'StockTake',
    component: () => import('@/views/stock/Take.vue'),
    meta: { title: '库存盘点', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'stock/take/detail/:id',
    name: 'StockTakeDetail',
    component: () => import('@/views/stock/TakeDetail.vue'),
    meta: { title: '盘点详情', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'stock/take/edit/:id',
    name: 'StockTakeEdit',
    component: () => import('@/views/stock/TakeCreate.vue'),
    meta: { title: '编辑盘点', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'stock/transfer',
    name: 'StockTransfer',
    component: () => import('@/views/stock/Transfer.vue'),
    meta: { title: '库存调拨', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: 'stock/warning',
    name: 'StockWarning',
    component: () => import('@/views/stock/Warning.vue'),
    meta: { title: '库存预警', showTabbar: false }
  }
]