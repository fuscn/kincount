// src/router/routes.js
export default [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/Index.vue'),
    meta: { title: '登录', noAuth: true }
  },
  {
    path: '/',
    redirect: '/dashboard',
    component: () => import('@/layout/MobileLayout.vue'), // 使用移动端布局
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/Index.vue'),
        meta: { title: '首页', icon: 'home-o' }
      },
      {
        path: '/product/skus',
        name: 'SkuList',
        component: () => import('@/views/product/sku/SkuList.vue'),
        meta: {
          title: 'SKU总列表',
          showLayoutNavBar: false // 可使用全局导航栏
        }
      },
      {
        path: '/product/:id/skus',
        name: 'ProductSkus',
        component: () => import('@/views/product/sku/Skus.vue'),
        meta: {
          title: 'SKU管理', showLayoutNavBar: false
        }
      },
      {
        path: '/product/:productId/skus/create',
        name: 'SkuCreate',
        component: () => import('@/views/product/sku/Form.vue'),
        meta: {
          title: '新增SKU', // 页面标题
          showTabbar: false, // 隐藏底部导航栏（同其他表单页）
          showLayoutNavBar: false // 使用页面内自定义导航栏
        }
      },
      {
        path: '/product/:productId/skus/edit/:skuId',
        name: 'SkuEdit',
        component: () => import('@/views/product/sku/Form.vue'),
        meta: {
          title: '编辑SKU', // 页面标题，浏览器标签和导航栏展示
          showTabbar: false, // 隐藏移动端底部Tab栏（表单页无需展示）
          showLayoutNavBar: false, // 不使用布局的公共导航栏，使用页面内自定义导航
          keepAlive: false // 无需缓存表单页，防止数据残留
        }
      },
      {
        path: 'product',
        name: 'Product',
        component: () => import('@/views/product/Index.vue'),
        meta: { title: '商品管理' }
      },
      {
        path: 'product/create',
        name: 'ProductCreate',
        component: () => import('@/views/product/Form.vue'),
        meta: {
          title: '新增商品',
          showTabbar: false,
          showLayoutNavBar: false
        }
      },
      {
        path: 'product/edit/:id',
        name: 'ProductEdit',
        component: () => import('@/views/product/Form.vue'),
        meta: {
          title: '编辑商品',
          showTabbar: false,
          showLayoutNavBar: false
        }
      },
      {
        path: 'category',
        name: 'Category',
        component: () => import('@/views/category/Index.vue'),
        meta: { title: '分类管理' } // 隐藏底部导航
      },
      {
        path: 'category/create',
        name: 'CategoryCreate',
        component: () => import('@/views/category/Form.vue'),
        meta: {
          title: '新增分类',
          showTabbar: false,
          showLayoutNavBar: false
        }
      },
      {
        path: 'category/edit/:id',
        name: 'CategoryEdit',
        component: () => import('@/views/category/Form.vue'),
        meta: {
          title: '编辑分类',
          showTabbar: false,
          showLayoutNavBar: false
        }
      },
      /* ===== 品牌管理 ===== */
      {
        path: 'brand',
        name: 'Brand',
        component: () => import('@/views/brand/Index.vue'),
        meta: { title: '品牌管理' }
      },
      {
        path: 'brand/create',
        name: 'BrandCreate',
        component: () => import('@/views/brand/Form.vue'),
        meta: { title: '新增品牌', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'brand/edit/:id',
        name: 'BrandEdit',
        component: () => import('@/views/brand/Form.vue'),
        meta: { title: '编辑品牌', showTabbar: false, showLayoutNavBar: false }
      },
      /* ===== 客户管理 ===== */
      {
        path: 'customer',
        name: 'Customer',
        component: () => import('@/views/customer/Index.vue'),
        meta: { title: '客户管理' }
      },
      {
        path: 'customer/create',
        name: 'CustomerCreate',
        component: () => import('@/views/customer/Form.vue'),
        meta: { title: '新增客户', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'customer/edit/:id',
        name: 'CustomerEdit',
        component: () => import('@/views/customer/Form.vue'),
        meta: { title: '编辑客户', showTabbar: false, showLayoutNavBar: false }
      },
      /* ===== 供应商管理 ===== */
      {
        path: 'supplier',
        name: 'Supplier',
        component: () => import('@/views/supplier/Index.vue'),
        meta: { title: '供应商管理' }
      },
      {
        path: 'supplier/create',
        name: 'SupplierCreate',
        component: () => import('@/views/supplier/Form.vue'),
        meta: { title: '新增供应商', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'supplier/edit/:id',
        name: 'SupplierEdit',
        component: () => import('@/views/supplier/Form.vue'),
        meta: { title: '编辑供应商', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'warehouse',
        name: 'Warehouse',
        component: () => import('@/views/warehouse/Index.vue'),
        meta: { title: '仓库管理' }
      },
      {
        path: 'warehouse/create',
        name: 'WarehouseCreate',
        component: () => import('@/views/warehouse/Form.vue'),
        meta: { title: '新增仓库', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'warehouse/edit/:id',
        name: 'WarehouseEdit',
        component: () => import('@/views/warehouse/Form.vue'),
        meta: { title: '编辑仓库', showTabbar: false, showLayoutNavBar: false }
      },
      // 采购管理
      {
        path: 'purchase/order',
        name: 'PurchaseOrder',
        component: () => import('@/views/Placeholder.vue'),
        meta: { title: '采购订单', showTabbar: false }
      },
      {
        path: 'purchase/stock',
        name: 'PurchaseStock',
        component: () => import('@/views/Placeholder.vue'),
        meta: { title: '采购入库', showTabbar: false }
      },
      // 销售管理
      {
        path: 'sale/order',
        name: 'SaleOrder',
        component: () => import('@/views/sale/order/Index.vue'),
        meta: { title: '销售订单' }
      },
      {
        path: 'sale/order/create',
        name: 'SaleOrderCreate',
        component: () => import('@/views/sale/order/Create.vue'),
        meta: { title: '新增销售订单', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'sale/order/detail/:id',
        name: 'SaleOrderDetail',
        component: () => import('@/views/sale/order/Detail.vue'),
        meta: { title: '销售订单详情', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'sale/stock',
        name: 'SaleStock',
        component: () => import('@/views/sale/stock/Index.vue'),
        meta: { title: '销售出库', showTabbar: false }
      },
      {
        path: 'sale/stock/create',
        name: 'SaleStockCreate',
        component: () => import('@/views/sale/stock/Create.vue'),
        meta: { title: '新增销售出库', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'sale/stock/detail/:id',
        name: 'SaleStockDetail',
        component: () => import('@/views/sale/stock/Detail.vue'),
        meta: { title: '销售出库详情', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: 'sale/return',
        name: 'SaleReturn',
        component: () => import('@/views/sale/return/Index.vue'),
        meta: { title: '销售退货', showTabbar: false }
      },
      {
        path: 'sale/return/create',
        name: 'SaleReturnCreate',
        component: () => import('@/views/sale/return/Create.vue'),
        meta: { title: '新建退货' }
      },
      {
        path: 'sale/return/detail/:id',
        name: 'SaleReturnDetail',
        component: () => import('@/views/sale/return/Detail.vue'),
        meta: { title: '退货详情', showTabbar: false, showLayoutNavBar: false }
      },
      // 库存管理
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
      },
      // 财务管理
      {
        path: 'financial/record',
        name: 'FinancialRecord',
        component: () => import('@/views/financial/FinancialRecord.vue'),
        meta: { title: '收支记录' }
      },
      {
        path: 'financial/record/create',
        name: 'FinancialRecordCreate',
        component: () => import('@/views/financial/FinancialRecordCreate.vue'),
        meta: {
          title: '新建收支记录', showTabbar: false, showLayoutNavBar: false
        }
      },
      {
        path: 'financial/record/detail/:id',
        name: 'FinancialRecordDetail',
        component: () => import('@/views/financial/FinancialRecordDetail.vue'),
        meta: {
          title: '收支记录详情',
          showTabbar: false,
          showLayoutNavBar: false
        }
      },
      // 如果需要编辑页面，也可以添加
      {
        path: 'financial/record/edit/:id',
        name: 'FinancialRecordEdit',
        component: () => import('@/views/financial/FinancialRecordEdit.vue'), // 需要创建这个组件
        meta: {
          title: '编辑收支记录',
          showTabbar: false,
          showLayoutNavBar: false
        }
      },
      {
        path: 'financial/report/profit',
        name: 'FinancialReportProfit',
        component: () => import('@/views/Placeholder.vue'),
        meta: { title: '利润报表', showTabbar: false }
      },
      {
        path: 'financial/report/cashflow',
        name: 'FinancialReportCashflow',
        component: () => import('@/views/Placeholder.vue'),
        meta: { title: '资金流水', showTabbar: false }
      },
      // 账款管理
      {
        path: 'account/receivable',
        name: 'AccountReceivable',
        component: () => import('@/views/Placeholder.vue'),
        meta: { title: '应收账款', showTabbar: false }
      },
      {
        path: 'account/payable',
        name: 'AccountPayable',
        component: () => import('@/views/Placeholder.vue'),
        meta: { title: '应付账款', showTabbar: false }
      },
      // 财务概览
      {
        path: 'financial/dashboard',
        name: 'FinancialDashboard',
        component: () => import('@/views/financial/FinancialDashboard.vue'),
        meta: { title: '财务概览', showTabbar: false }
      },
      {
        path: 'account/payable/create',
        name: 'AccountPayableCreate',
        component: () => import('@/views/financial/AccountPayableCreate.vue'), // 需要创建这个组件
        meta: { title: '新增应付记录', showTabbar: false }
      },
      // 系统管理
      // 在 src/router/routes.js 里追加即可
      {
        path: '/system/user',
        name: 'SystemUser',
        component: () => import('@/views/system/user/Index.vue'),
        meta: { title: '用户管理' }
      },
      {
        path: '/system/user/create',
        name: 'SystemUserCreate',
        component: () => import('@/views/system/user/Form.vue'),
        meta: { title: '新增用户', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: '/system/user/edit/:id',
        name: 'SystemUserEdit',
        component: () => import('@/views/system/user/Form.vue'),
        meta: { title: '编辑用户', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: '/system/user/password',
        name: 'SystemUserPassword',
        component: () => import('@/views/system/user/Password.vue'),
        meta: { title: '修改密码', showTabbar: false, showLayoutNavBar: false }
      },
      {
        path: '/system/user/profile',
        name: 'Profile',
        component: () => import('@/views/system/user/Profile.vue'),
        meta: { title: '我的资料', showTabbar: false, showLayoutNavBar: false }
      },
      // router/index.js 或 router/routes.js
      {
        path: '/system/user/profile/edit',
        name: 'ProfileEdit',
        component: () => import('@/views/system/user/ProfileEdit.vue'),
        meta: {
          title: '编辑资料', showTabbar: false, showLayoutNavBar: false
        }
      },
      {
        path: 'system/role',
        name: 'SystemRole',
        component: () => import('@/views/Placeholder.vue'),
        meta: { title: '角色权限', showTabbar: false }
      },
      {
        path: 'system/config',
        name: 'SystemConfig',
        component: () => import('@/views/Placeholder.vue'),
        meta: { title: '系统配置', showTabbar: false }
      }
    ]
  },
  {
    path: '/redirect/:path(.*)',
    name: 'Redirect',
    component: () => import('@/layout/Redirect.vue'),
    meta: { hidden: true }
  },
  {
    path: '/:pathMatch(.*)*',
    name: '404',
    component: () => import('@/views/error/404.vue'),
    meta: { noAuth: true }
  }
]