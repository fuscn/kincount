export default [
  {
    path: '/product/:productId/skus',
    name: 'ProductSkus',
    component: () => import('@/views/product/SkuForm.vue'),
    meta: {
      title: 'SKU管理', 
      showLayoutNavBar: false
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
    component: () => import('@/views/product/ProductForm.vue'),
    meta: {
      title: '新增商品',
      showTabbar: false,
      showLayoutNavBar: false
    }
  },
  {
    path: 'product/edit/:id',
    name: 'ProductEdit',
    component: () => import('@/views/product/ProductForm.vue'),
    meta: {
      title: '编辑商品',
      showTabbar: false,
      showLayoutNavBar: false
    }
  }
]